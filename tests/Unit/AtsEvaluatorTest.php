<?php

namespace Tests\Unit;

use App\Services\Ats\AtsEvaluator;
use App\Services\Ats\ExtractedCv;
use App\Services\Ats\InvalidModelResponseException;
use App\Services\Ats\LlmClient;
use App\Services\Ats\ModelNotAllowedException;
use App\Services\Ats\ModelResponseValidator;
use App\Services\Ats\SkillMatcher;
use PHPUnit\Framework\TestCase;


class FakeLlm implements LlmClient
{
    public int $calls = 0;

    public function __construct(private array $replies)
    {
    }

    public function generate(string $system, string $prompt, string $model): string
    {
        $this->calls++;
        return array_shift($this->replies) ?? '{}';
    }
}

class AtsEvaluatorTest extends TestCase
{
    private const CV = "Backend developer.\nSkills: PHP, Laravel, JavaScript, MySQL\nBuilt REST APIs serving 10k users at Acme Corp.";

    private function config(): array
    {
        return [
            'ollama' => ['default_model' => 'llama3.2', 'allowed_models' => ['llama3.2'], 'max_attempts' => 3],
            'scoring' => [
                'weights' => ['skills' => 0.6, 'experience' => 0.3, 'education' => 0.1],
                'levels' => ['strong' => 1.0, 'partial' => 0.6, 'weak' => 0.25, 'none' => 0.0],
                'recommendation' => ['strong' => 75, 'moderate' => 50],
            ],
        ];
    }

    private function cv(string $text = self::CV): ExtractedCv
    {
        return new ExtractedCv($text, $text, [], 'test', false, false);
    }

    private function evaluator(array $replies, ?FakeLlm &$llm = null): AtsEvaluator
    {
        $llm = new FakeLlm($replies);
        $matcher = new SkillMatcher(require __DIR__ . '/../../config/ats_skills.php');
        return new AtsEvaluator($llm, $matcher, new ModelResponseValidator(), $this->config());
    }

    private function reply(array $override = []): string
    {
        return json_encode(array_merge([
            'jd_required_skills' => ['PHP', 'Java'],
            'experience_fit' => 'strong',
            'experience_match' => 'Relevant backend experience.',
            'education_fit' => 'not_required',
            'education_match' => 'Not mentioned.',
            'strengths' => [['text' => 'Builds APIs at scale', 'evidence' => 'Built REST APIs serving 10k users']],
            'weaknesses' => ['No cloud experience listed'],
            'summary' => 'Good fit.',
        ], $override));
    }

    public function test_retries_on_invalid_json_then_succeeds(): void
    {
        $ev = $this->evaluator(['this is not json', $this->reply()], $llm);
        $r = $ev->evaluate($this->cv(), 'We need PHP and Java developers.');

        $this->assertSame(2, $r['attempts']);
        $this->assertSame(2, $llm->calls);
    }

    public function test_throws_after_max_attempts(): void
    {
        $ev = $this->evaluator(['bad', 'bad', 'bad'], $llm);

        $this->expectException(InvalidModelResponseException::class);
        $ev->evaluate($this->cv(), 'We need PHP.');
    }

    public function test_java_is_missing_even_if_cv_has_javascript(): void
    {
        $ev = $this->evaluator([$this->reply()]);
        $r = $ev->evaluate($this->cv(), 'We need PHP and Java developers.');

        $this->assertSame(['Java'], $r['missing_skills']);
        $this->assertSame('PHP', $r['matched_skills'][0]['skill']);
    }

    public function test_skill_not_mentioned_in_jd_is_discarded(): void
    {
        $ev = $this->evaluator([$this->reply(['jd_required_skills' => ['PHP', 'Kubernetes']])]);
        $r = $ev->evaluate($this->cv(), 'We need a PHP developer.');

        $this->assertSame([], $r['missing_skills']); // Kubernetes اخترعها الموديل
    }

    public function test_strength_with_fake_quote_is_dropped(): void
    {
        $ev = $this->evaluator([$this->reply(['strengths' => [
            ['text' => 'Real', 'evidence' => 'Built REST APIs serving 10k users'],
            ['text' => 'Invented', 'evidence' => 'Led a team of 50 engineers at Google'],
        ]])]);
        $r = $ev->evaluate($this->cv(), 'We need PHP.');

        $this->assertCount(1, $r['strengths']);
        $this->assertSame(1, $r['dropped_unverified_strengths']);
    }

    public function test_model_score_is_ignored_and_score_is_computed(): void
    {
        
        $ev = $this->evaluator([$this->reply(['score' => 100])]);
        $r = $ev->evaluate($this->cv(), 'We need PHP and Java developers.');

        
        $this->assertEqualsWithDelta(66.67, $r['score'], 0.01);
        $this->assertSame('Moderate Match', $r['recommendation']);
    }

    public function test_job_skills_from_database_take_priority(): void
    {
        $ev = $this->evaluator([$this->reply()]);
        $r = $ev->evaluate($this->cv(), 'anything', null, ['Laravel', 'MySQL']);

        $this->assertSame('job', $r['skills_source']);
        $this->assertSame([], $r['missing_skills']);
    }

    public function test_html_in_model_output_is_stripped(): void
    {
        $ev = $this->evaluator([$this->reply(['summary' => 'Good <img src=x onerror=alert(1)> fit'])]);
        $r = $ev->evaluate($this->cv(), 'We need PHP.');

        $this->assertStringNotContainsString('<', $r['summary']);
    }

    public function test_model_must_be_in_allowed_list(): void
    {
        $ev = $this->evaluator([$this->reply()]);

        $this->expectException(ModelNotAllowedException::class);
        $ev->evaluate($this->cv(), 'We need PHP.', null, null, 'some-huge-model:70b');
    }

    public function test_invalid_level_is_rejected(): void
    {
        $ev = $this->evaluator([$this->reply(['experience_fit' => 'amazing']), $this->reply()], $llm);
        $r = $ev->evaluate($this->cv(), 'We need PHP.');

        $this->assertSame(2, $r['attempts']);
    }
}
