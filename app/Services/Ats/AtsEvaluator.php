<?php

namespace App\Services\Ats;

/**
 * المنسّق: الموديل -> تحقق -> مطابقة مهارات حتمية -> سكور بالكود.
 *
 * ما هو مضمون (محسوب أو متحقق منه في الكود):
 *  - matched_skills / missing_skills (SkillMatcher على النص الكامل)
 *  - أن كل مهارة مطلوبة مذكورة فعلًا في الـ JD
 *  - أن كل strength عليها اقتباس موجود فعلًا في الـ CV
 *  - السكور والـ recommendation
 * ما هو رأي الموديل ومش متحقق منه: summary, experience_match, education_match, weaknesses
 */
class AtsEvaluator
{
    public const UNVERIFIED_FIELDS = ['summary', 'experience_match', 'education_match', 'weaknesses'];

    public function __construct(
        private LlmClient $llm,
        private SkillMatcher $matcher,
        private ModelResponseValidator $validator,
        private array $config = [],
    ) {
        $this->config = $config ?: (array) config('ats');
    }

    /**
     * @param string[]|null $requiredSkills مهارات الوظيفة من الداتابيز لو متاحة (الأدق).
     *                                      لو null بنستخدم الي الموديل استخرجها من الـ JD.
     * @return array<string,mixed>
     */
    public function evaluate(
        ExtractedCv $cv,
        string $jobDescription,
        ?string $jobTitle = null,
        ?array $requiredSkills = null,
        ?string $model = null,
    ): array {
        $model = $this->resolveModel($model);

        $basePrompt = AtsPrompt::user(
            $jobDescription,
            $cv->textForModel,
            $jobTitle,
            $cv->boldSupported ? $cv->boldKeywords : [],
        );

        [$parsed, $attempts] = $this->callModel($basePrompt, $model);

        // 1) المهارات المطلوبة
        $skills = $requiredSkills !== null
            ? $this->dedupe($requiredSkills)
            : $this->skillsActuallyInJd($parsed['jd_required_skills'], $jobDescription);

        // 2) مطابقة حتمية على النص الكامل (مش المقصوص)
        $match = $this->matcher->partition($skills, $cv->text);

        // 3) التحقق من اقتباسات الـ strengths
        [$strengths, $dropped] = $this->verifiedStrengths($parsed['strengths'], $cv->text);

        // 4) السكور
        $breakdown = $this->score(
            matched: count($match['matched']),
            total: count($match['matched']) + count($match['missing']),
            experienceFit: $parsed['experience_fit'],
            educationFit: $parsed['education_fit'],
        );

        return [
            'score' => $breakdown['score'],
            'recommendation' => $this->recommendation($breakdown['score']),
            'score_breakdown' => $breakdown['components'],
            'matched_skills' => $match['matched'],
            'missing_skills' => $match['missing'],
            'experience_match' => $parsed['experience_match'],
            'education_match' => $parsed['education_match'],
            'strengths' => $strengths,
            'dropped_unverified_strengths' => $dropped,
            'weaknesses' => $parsed['weaknesses'],
            'summary' => $parsed['summary'],
            'unverified_fields' => self::UNVERIFIED_FIELDS,
            'skills_source' => $requiredSkills !== null ? 'job' : 'model_from_jd',
            'cv_truncated' => $cv->truncated,
            'model_version' => 'ollama:' . $model,
            'attempts' => $attempts,
            'run_status' => 'ok',
        ];
    }

    // ------------------------------------------------------------ model call

    public function resolveModel(?string $requested): string
    {
        $default = (string) ($this->config['ollama']['default_model'] ?? 'llama3.2');
        $allowed = (array) ($this->config['ollama']['allowed_models'] ?? [$default]);

        $model = ($requested === null || $requested === '') ? $default : $requested;

        if (! in_array($model, $allowed, true)) {
            throw new ModelNotAllowedException("الموديل '{$model}' غير مسموح به.");
        }
        return $model;
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private function callModel(string $basePrompt, string $model): array
    {
        $max = max(1, (int) ($this->config['ollama']['max_attempts'] ?? 3));
        $prompt = $basePrompt;
        $lastError = 'unknown';

        for ($attempt = 1; $attempt <= $max; $attempt++) {
            $raw = $this->llm->generate(AtsPrompt::SYSTEM, $prompt, $model);

            try {
                return [$this->validator->parse($raw), $attempt];
            } catch (InvalidModelResponseException $e) {
                $lastError = $e->getMessage();
                $prompt = AtsPrompt::retry($basePrompt, $lastError);
            }
        }

        throw new InvalidModelResponseException(
            "الموديل لم يُرجع رد صالحًا بعد {$max} محاولات. آخر خطأ: {$lastError}"
        );
    }

    // ---------------------------------------------------------- verification

    /**
     * يمنع الموديل من اختراع متطلبات: المهارة لازم تكون مكتوبة فعلًا في الـ JD.
     * @param  string[] $skills
     * @return string[]
     */
    private function skillsActuallyInJd(array $skills, string $jobDescription): array
    {
        $jd = SkillMatcher::normalize($jobDescription);
        $kept = [];
        foreach ($skills as $skill) {
            [$head] = SkillMatcher::splitComposite($skill);
            if ($this->matcher->match($head, $jd) !== null) {
                $kept[] = $skill;
            }
        }
        return $this->dedupe($kept);
    }

    /**
     * @param  array<int,array{text:string,evidence:string}> $strengths
     * @return array{0:array<int,array{text:string,evidence:string}>,1:int}
     */
    private function verifiedStrengths(array $strengths, string $cvText): array
    {
        $cv = $this->squash($cvText);
        $kept = [];
        $dropped = 0;

        foreach ($strengths as $s) {
            $quote = $this->squash($s['evidence']);
            if (mb_strlen($quote) >= 8 && str_contains($cv, $quote)) {
                $kept[] = $s;
            } else {
                $dropped++;
            }
        }
        return [$kept, $dropped];
    }

    /** تطبيع للمقارنة: lowercase، بدون **، وبدون علامات ترقيم/مسافات زيادة */
    private function squash(string $text): string
    {
        $t = SkillMatcher::normalize($text);
        $t = preg_replace('/[^\p{L}\p{N}+#]+/u', ' ', $t) ?? $t;
        return trim($t);
    }

    // --------------------------------------------------------------- scoring

    /**
     * @return array{score:float,components:array<string,mixed>}
     */
    public function score(int $matched, int $total, string $experienceFit, string $educationFit): array
    {
        $levels = (array) ($this->config['scoring']['levels'] ?? []);
        $weights = (array) ($this->config['scoring']['weights'] ?? []);

        $values = [
            'skills' => $total > 0 ? $matched / $total : null,
            'experience' => $levels[$experienceFit] ?? 0.0,
            'education' => $educationFit === 'not_required' ? null : ($levels[$educationFit] ?? 0.0),
        ];

        $sumW = 0.0;
        $acc = 0.0;
        $components = [];
        foreach ($values as $name => $value) {
            $w = (float) ($weights[$name] ?? 0);
            if ($value === null || $w <= 0) {
                $components[$name] = ['value' => null, 'weight' => 0.0];
                continue;
            }
            $sumW += $w;
            $acc += $w * $value;
            $components[$name] = ['value' => round($value, 4), 'weight' => $w];
        }

        $score = $sumW > 0 ? ($acc / $sumW) * 100 : 0.0;
        foreach ($components as $name => $c) {
            if ($c['value'] !== null) {
                $components[$name]['effective_weight'] = round($c['weight'] / $sumW, 4);
            }
        }

        return ['score' => round(max(0.0, min(100.0, $score)), 2), 'components' => $components];
    }

    private function recommendation(float $score): string
    {
        $t = (array) ($this->config['scoring']['recommendation'] ?? ['strong' => 75, 'moderate' => 50]);

        return match (true) {
            $score >= $t['strong'] => 'Strong Match',
            $score >= $t['moderate'] => 'Moderate Match',
            default => 'Weak Match',
        };
    }

    /** @param string[] $skills @return string[] */
    private function dedupe(array $skills): array
    {
        $out = [];
        foreach ($skills as $s) {
            $s = trim((string) $s);
            if ($s !== '') {
                $out[mb_strtolower($s)] = $s;
            }
        }
        return array_values($out);
    }
}
