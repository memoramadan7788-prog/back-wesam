<?php

namespace App\Services\Ats;

use DOMDocument;
use DOMXPath;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * استخراج نص الـ CV من ملف (pdf/docx/txt/md) أو من نص ملصوق.
 *
 * - الـ contract واحد للمصدرين: ExtractedCv، والـ Bold بيتمثل بـ **نص** في الحالتين.
 * - الـ PDF: النص بس (smalot مش بيكشف Bold). boldSupported = false.
 * - الـ DOCX: بيكشف Bold من w:rPr/w:b بدون أي مكتبة إضافية.
 */
class CvTextExtractor
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function __construct(
        private int $minChars = 50,
        private int $maxCharsForModel = 12000,
        private array $allowedExtensions = ['pdf', 'docx', 'txt', 'md'],
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) config('ats.cv.min_chars', 50),
            (int) config('ats.cv.max_chars_for_model', 12000),
            (array) config('ats.cv.allowed_extensions', ['pdf', 'docx', 'txt', 'md']),
        );
    }

    /** نص ملصوق: نفس المعالجة، والـ Bold لو المستخدم كتبه **هكذا**. */
    public function fromPastedText(string $text): ExtractedCv
    {
        return $this->build($text, [], 'pasted-text', boldSupported: true, parseMarkdownBold: true);
    }

    public function fromFile(string $path, string $originalName): ExtractedCv
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === 'doc') {
            throw new CvExtractionException('صيغة .doc القديمة غير مدعومة. احفظ الملف كـ .docx أو PDF.');
        }
        if (! in_array($ext, $this->allowedExtensions, true)) {
            throw new CvExtractionException("صيغة الملف '.{$ext}' غير مدعومة.");
        }
        if (! is_readable($path)) {
            throw new CvExtractionException('تعذر قراءة الملف المرفوع.');
        }

        $head = (string) file_get_contents($path, false, null, 0, 4);

        return match ($ext) {
            'pdf' => $this->fromPdf($path, $head),
            'docx' => $this->fromDocx($path, $head),
            default => $this->build(
                (string) file_get_contents($path),
                [],
                'plain-text',
                boldSupported: true,
                parseMarkdownBold: true,
            ),
        };
    }

    // ------------------------------------------------------------------ PDF

    private function fromPdf(string $path, string $head): ExtractedCv
    {
        if (! str_starts_with($head, '%PDF')) {
            throw new CvExtractionException('الملف ليس PDF صالحًا.');
        }

        try {
            $text = (new PdfParser())->parseFile($path)->getText();
        } catch (Throwable $e) {
            throw new CvExtractionException('تعذر قراءة ملف الـ PDF (قد يكون تالفًا أو محميًا بكلمة سر).', 0, $e);
        }

        return $this->build($text, [], 'pdf (smalot/pdfparser)', boldSupported: false);
    }

    // ----------------------------------------------------------------- DOCX

    private function fromDocx(string $path, string $head): ExtractedCv
    {
        if (! str_starts_with($head, 'PK')) {
            throw new CvExtractionException('الملف ليس DOCX صالحًا.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new CvExtractionException('تعذر فتح ملف الـ DOCX.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new CvExtractionException('ملف الـ DOCX لا يحتوي على نص.');
        }

        $dom = new DOMDocument();
        // LIBXML_NONET يمنع تحميل أي موارد خارجية (حماية من XXE)
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new CvExtractionException('ملف الـ DOCX تالف.');
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::WORD_NS);

        $lines = [];
        $bold = [];

        // //w:p بترجع كل الفقرات بترتيبها، ومنها فقرات الجداول
        foreach ($xp->query('//w:p') as $p) {
            $segments = [];
            foreach ($xp->query('.//w:r', $p) as $run) {
                $segments[] = [$this->runText($xp, $run), $this->isBold($xp, $run)];
            }
            $line = $this->renderParagraph($segments, $bold);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $this->build(implode("\n", $lines), $bold, 'docx (zip+xml)', boldSupported: true);
    }

    private function runText(DOMXPath $xp, \DOMNode $run): string
    {
        $out = '';
        foreach ($xp->query('./w:t | ./w:tab | ./w:br', $run) as $n) {
            $out .= match ($n->localName) {
                'tab' => ' ',
                'br' => "\n",
                default => $n->textContent,
            };
        }
        return $out;
    }

    private function isBold(DOMXPath $xp, \DOMNode $run): bool
    {
        $b = $xp->query('./w:rPr/w:b', $run)->item(0);
        if (! $b) {
            return false;
        }
        $val = $b->attributes?->getNamedItemNS(self::WORD_NS, 'val')?->nodeValue;
        return ! in_array(strtolower((string) $val), ['0', 'false', 'off'], true);
    }

    /**
     * Word بيقسّم الجملة لـ runs كتير، فبندمج الـ runs المتجاورة من نفس النوع
     * قبل ما نلفها بـ ** (علشان ما يطلعش **Java****Script**).
     *
     * @param array<int,array{0:string,1:bool}> $segments
     * @param string[] $bold
     */
    private function renderParagraph(array $segments, array &$bold): string
    {
        $merged = [];
        foreach ($segments as [$text, $isBold]) {
            if ($text === '') {
                continue;
            }
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last][1] === $isBold) {
                $merged[$last][0] .= $text;
            } else {
                $merged[] = [$text, $isBold];
            }
        }

        $line = '';
        foreach ($merged as [$text, $isBold]) {
            $trimmed = trim($text);
            if ($isBold && mb_strlen($trimmed) > 1) {
                $bold[] = $trimmed;
                // نحافظ على المسافات حوالين الجزء الـ Bold
                $lead = substr($text, 0, strlen($text) - strlen(ltrim($text)));
                $trail = substr($text, strlen(rtrim($text)));
                $line .= $lead . '**' . $trimmed . '**' . $trail;
            } else {
                $line .= $text;
            }
        }

        return trim($line);
    }

    // ---------------------------------------------------------------- common

    /** @param string[] $bold */
    private function build(
        string $raw,
        array $bold,
        string $engine,
        bool $boldSupported,
        bool $parseMarkdownBold = false,
    ): ExtractedCv {
        $text = $this->clean($raw);

        if (mb_strlen($text) < $this->minChars) {
            throw new CvExtractionException(
                'لم نتمكن من استخراج نص كافٍ من الـ CV. إن كان الملف PDF ممسوحًا ضوئيًا (صورة) فارفع نسخة نصية.'
            );
        }

        if ($parseMarkdownBold && preg_match_all('/\*\*(.+?)\*\*/u', $text, $m)) {
            $bold = array_map('trim', $m[1]);
        }
        $bold = array_values(array_unique(array_filter($bold, fn ($b) => mb_strlen($b) > 1)));

        [$forModel, $truncated] = $this->truncate($text);

        return new ExtractedCv($text, $forModel, $bold, $engine, $truncated, $boldSupported);
    }

    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // شيل رموز التحكم ما عدا \n و \t
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ ?\n ?/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** @return array{0:string,1:bool} */
    private function truncate(string $text): array
    {
        if (mb_strlen($text) <= $this->maxCharsForModel) {
            return [$text, false];
        }

        $cut = mb_substr($text, 0, $this->maxCharsForModel);
        // حاول تقص عند آخر سطر بدل منتصف جملة (لو في آخر 30%)
        $pos = mb_strrpos($cut, "\n");
        if ($pos !== false && $pos > (int) ($this->maxCharsForModel * 0.7)) {
            $cut = mb_substr($cut, 0, $pos);
        }

        return [rtrim($cut), true];
    }
}
