<?php

namespace App\Services\Ats;

/**
 * مطابقة مهارة مطلوبة مع نص الـ CV بدون substring.
 * القاعدة: المهارة (أو أي alias) لازم تظهر ككلمة كاملة.
 *  - "java"  لا تطابق "javascript"
 *  - "sql"   لا تطابق "mysql"
 *  - "react native" لازم العبارة كاملة، وجود "react" لوحده مش كفاية
 */
class SkillMatcher
{
    /** @var array<string, array{aliases?: string[], implied_by?: string[]}> */
    private array $dictionary;

    /** @var array<string, string> alias/اسم => المفتاح الأساسي */
    private array $canonicalIndex = [];

    public function __construct(?array $dictionary = null)
    {
        $this->dictionary = $dictionary ?? (function_exists('config') ? config('ats_skills', []) : []);

        foreach ($this->dictionary as $canonical => $def) {
            $this->canonicalIndex[$canonical] = $canonical;
            foreach ($def['aliases'] ?? [] as $alias) {
                $this->canonicalIndex[self::normalize($alias)] = $canonical;
            }
        }
    }

    /**
     * @param  string[] $skills
     * @return array{matched: array<int,array{skill:string,via:string,evidence:string}>, missing: string[]}
     */
    public function partition(array $skills, string $cvText): array
    {
        $cv = self::normalize($cvText);
        $matched = [];
        $missing = [];
        $seen = [];

        foreach ($skills as $skill) {
            $skill = trim((string) $skill);
            $key = self::normalize($skill);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $hit = $this->match($skill, $cv);
            if ($hit) {
                $matched[] = ['skill' => $skill] + $hit;
            } else {
                $missing[] = $skill;
            }
        }

        return ['matched' => $matched, 'missing' => $missing];
    }

    /** @return array{via:string,evidence:string}|null  ($cv لازم يكون normalized) */
    public function match(string $skill, string $cv): ?array
    {
        [$head, $subItems] = self::splitComposite($skill);
        $head = self::normalize($head);
        if ($head === '' || mb_strlen($head) < 2) {
            return null;
        }

        $canonical = $this->canonicalIndex[$head] ?? $head;
        $def = $this->dictionary[$canonical] ?? [];

        // 1) الاسم نفسه ككلمة كاملة
        if (self::containsTerm($cv, $head)) {
            return ['via' => 'exact', 'evidence' => $head];
        }
        if ($canonical !== $head && self::containsTerm($cv, $canonical)) {
            return ['via' => 'alias', 'evidence' => $canonical];
        }

        // 2) aliases
        foreach ($def['aliases'] ?? [] as $alias) {
            $alias = self::normalize($alias);
            if (self::containsTerm($cv, $alias)) {
                return ['via' => 'alias', 'evidence' => $alias];
            }
        }

        // 3) implied_by (مثال: S3 => AWS)
        foreach ($def['implied_by'] ?? [] as $term) {
            $term = self::normalize($term);
            if (self::containsTerm($cv, $term)) {
                return ['via' => 'implied', 'evidence' => $term];
            }
        }

        // 4) مهارة مركبة "AWS (EC2, S3)": الاسم الرئيسي مش موجود،
        //    نقبلها لو كل العناصر الفرعية موجودة (مش 50% زي القديم).
        if ($subItems !== []) {
            foreach ($subItems as $sub) {
                if (! self::containsTerm($cv, self::normalize($sub))) {
                    return null;
                }
            }
            return ['via' => 'sub_items', 'evidence' => implode(', ', $subItems)];
        }

        return null;
    }

    /** "AWS (EC2, S3)" => ["AWS", ["EC2","S3"]] */
    public static function splitComposite(string $skill): array
    {
        if (preg_match('/^\s*([^()]+?)\s*\(([^)]*)\)\s*$/u', $skill, $m)) {
            $subs = array_values(array_filter(array_map('trim', preg_split('/[,;\/]/u', $m[2]))));
            return [$m[1], $subs];
        }
        return [$skill, []];
    }

    public static function normalize(string $text): string
    {
        $t = mb_strtolower($text, 'UTF-8');
        $t = str_replace(['**', '__', "\u{2013}", "\u{2014}"], ['', '', '-', '-'], $t);
        $t = preg_replace('/\s+/u', ' ', $t);
        return trim($t);
    }

    /** هل الـ term موجود ككلمة كاملة؟ بيتعامل صح مع c++ و c# و node.js و ci/cd */
    public static function containsTerm(string $haystack, string $term): bool
    {
        if ($term === '') {
            return false;
        }
        $pattern = preg_quote($term, '/');
        $pattern = str_replace(['\ ', ' '], '\s+', $pattern);

        // قبل: مش حرف/رقم/+/#/.   بعد: مش حرف/رقم/+/#  ولا ".حرف" (يمنع node من node.js)
        $regex = '/(?<![a-z0-9+#.])' . $pattern . '(?![a-z0-9+#])(?!\.[a-z0-9])/u';

        return (bool) preg_match($regex, $haystack);
    }
}
