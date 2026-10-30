<?php

namespace App\Services\Ats;

/**
 * يستخرج JSON من رد الموديل ويتحقق من شكله وقيمه، وينضّف النصوص.
 * أي مشكلة => InvalidModelResponseException برسالة بتتبعت للموديل في إعادة المحاولة.
 */
class ModelResponseValidator
{
    private const LEVELS = ['strong', 'partial', 'weak', 'none'];
    private const MAX_TEXT = 600;
    private const MAX_SKILLS = 40;
    private const MAX_ITEMS = 6;

    /** @return array<string,mixed> */
    public function parse(string $raw): array
    {
        $text = trim($raw);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/u', $text, $m)) {
            $text = trim($m[1]);
        } else {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $data = json_decode($text, true);
        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidModelResponseException('الرد ليس JSON object صالح.');
        }

        return $this->validate($data);
    }

    /** @return array<string,mixed> */
    public function validate(array $d): array
    {
        $experienceFit = $this->level($d, 'experience_fit', allowNotRequired: false);
        $educationFit = $this->level($d, 'education_fit', allowNotRequired: true);

        $strengths = [];
        foreach ((array) ($d['strengths'] ?? []) as $item) {
            if (! is_array($item)) {
                continue; // الشكل القديم (نص بدون evidence) مرفوض: مفيش دليل = مفيش strength
            }
            $text = $this->clean($item['text'] ?? '');
            $evidence = $this->clean($item['evidence'] ?? '');
            if ($text !== '' && $evidence !== '') {
                $strengths[] = ['text' => $text, 'evidence' => $evidence];
            }
        }

        return [
            'jd_required_skills' => $this->stringList($d['jd_required_skills'] ?? [], self::MAX_SKILLS, 80),
            'experience_fit' => $experienceFit,
            'experience_match' => $this->clean($d['experience_match'] ?? ''),
            'education_fit' => $educationFit,
            'education_match' => $this->clean($d['education_match'] ?? ''),
            'strengths' => array_slice($strengths, 0, self::MAX_ITEMS),
            'weaknesses' => $this->stringList($d['weaknesses'] ?? [], self::MAX_ITEMS, self::MAX_TEXT),
            'summary' => $this->clean($d['summary'] ?? ''),
        ];
    }

    private function level(array $d, string $key, bool $allowNotRequired): string
    {
        if (! isset($d[$key]) || ! is_string($d[$key])) {
            throw new InvalidModelResponseException("الحقل '{$key}' مفقود.");
        }
        $v = strtolower(trim($d[$key]));
        $allowed = $allowNotRequired ? [...self::LEVELS, 'not_required'] : self::LEVELS;

        if (! in_array($v, $allowed, true)) {
            throw new InvalidModelResponseException(
                "قيمة '{$key}' غير صالحة. المسموح: " . implode(', ', $allowed) . '.'
            );
        }
        return $v;
    }

    /** @return string[] */
    private function stringList(mixed $value, int $maxItems, int $maxLen): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $clean = $this->clean($item, $maxLen);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }
        return array_slice(array_values(array_unique($out)), 0, $maxItems);
    }

    /** يشيل أي HTML ويقص الطول (دفاعًا إضافيًا ضد XSS، والواجهة لازم تعرض كنص برضه) */
    private function clean(mixed $value, int $maxLen = self::MAX_TEXT): string
    {
        if (! is_string($value)) {
            return '';
        }
        $text = trim(strip_tags($value));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_substr($text, 0, $maxLen);
    }
}
