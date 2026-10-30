<?php

namespace App\Services\Ats;

/**
 * نتيجة استخراج النص. نفس الشكل سواء جاي من ملف أو من نص ملصوق.
 */
final class ExtractedCv
{
    /**
     * @param string   $text           النص الكامل (يُستخدم في التحقق من المهارات)
     * @param string   $textForModel   النص بعد تطبيق حد الطول (يُرسل للموديل)
     * @param string[] $boldKeywords   الكلمات المكتوبة Bold (فاضية لو المصدر مش بيدعمها)
     * @param string   $engine         اسم طريقة الاستخراج
     * @param bool     $truncated      هل اتقص النص قبل الإرسال للموديل؟
     * @param bool     $boldSupported  هل المصدر بيدعم اكتشاف Bold أصلًا؟
     */
    public function __construct(
        public readonly string $text,
        public readonly string $textForModel,
        public readonly array $boldKeywords,
        public readonly string $engine,
        public readonly bool $truncated,
        public readonly bool $boldSupported,
    ) {
    }

    public function charCount(): int
    {
        return mb_strlen($this->text);
    }
}
