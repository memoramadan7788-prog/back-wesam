<?php

namespace Tests\Unit;

use App\Services\Ats\CvExtractionException;
use App\Services\Ats\CvTextExtractor;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class CvTextExtractorTest extends TestCase
{
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
    }

    private function tmpFile(string $ext, string $content = ''): string
    {
        $f = tempnam(sys_get_temp_dir(), 'cv') . '.' . $ext;
        file_put_contents($f, $content);
        $this->tmp[] = $f;
        return $f;
    }

   
    private function makeDocx(array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $runs) {
            $body .= '<w:p>';
            foreach ($runs as [$text, $bold]) {
                $rpr = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
                $body .= "<w:r>{$rpr}<w:t xml:space=\"preserve\">{$text}</w:t></w:r>";
            }
            $body .= '</w:p>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $body . '</w:body></w:document>';

        $path = $this->tmpFile('docx');
        @unlink($path);
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
        $this->tmp[] = $path;
        return $path;
    }

    private function extractor(int $max = 12000): CvTextExtractor
    {
        return new CvTextExtractor(minChars: 20, maxCharsForModel: $max);
    }

    public function test_docx_bold_runs_are_merged_and_collected(): void
    {
        $path = $this->makeDocx([
            [['Skills: ', false], ['Java', true], ['Script', true], [' and PHP', false]],
            [['Worked at some company for five years building APIs.', false]],
        ]);

        $cv = $this->extractor()->fromFile($path, 'cv.docx');

        $this->assertStringContainsString('**JavaScript**', $cv->text);
        $this->assertStringNotContainsString('****', $cv->text);
        $this->assertSame(['JavaScript'], $cv->boldKeywords);
        $this->assertTrue($cv->boldSupported);
    }

    public function test_pasted_text_uses_same_bold_contract(): void
    {
        $cv = $this->extractor()->fromPastedText("Skills: **Laravel**, **MySQL**\nBuilt many web applications.");

        $this->assertSame(['Laravel', 'MySQL'], $cv->boldKeywords);
        $this->assertSame('pasted-text', $cv->engine);
    }

    public function test_long_text_is_truncated_for_model_but_kept_full(): void
    {
        $text = str_repeat("Line of experience with various technologies.\n", 100);
        $cv = $this->extractor(500)->fromPastedText($text);

        $this->assertTrue($cv->truncated);
        $this->assertLessThanOrEqual(500, mb_strlen($cv->textForModel));
        $this->assertGreaterThan(500, mb_strlen($cv->text));
    }

    public function test_short_text_is_rejected(): void
    {
        $this->expectException(CvExtractionException::class);
        $this->extractor()->fromPastedText('hi');
    }

    public function test_legacy_doc_and_unknown_formats_are_rejected(): void
    {
        $this->expectException(CvExtractionException::class);
        $this->extractor()->fromFile($this->tmpFile('doc', 'x'), 'cv.doc');
    }

    public function test_fake_pdf_is_rejected(): void
    {
        $this->expectException(CvExtractionException::class);
        $this->extractor()->fromFile($this->tmpFile('pdf', 'not a pdf'), 'cv.pdf');
    }

    public function test_fake_docx_is_rejected(): void
    {
        $this->expectException(CvExtractionException::class);
        $this->extractor()->fromFile($this->tmpFile('docx', 'not a zip'), 'cv.docx');
    }
}
