<?php

namespace App\Services\Ats;

/**
 * واجهة بسيطة للموديل. بتخلينا نختبر AtsEvaluator بعميل مزيّف
 * ونبدّل Ollama بغيره بعدين من غير ما نلمس باقي الكود.
 */
interface LlmClient
{
    /**
     * @throws OllamaUnavailableException لو الخدمة مش متاحة أو الموديل مش موجود
     */
    public function generate(string $system, string $prompt, string $model): string;
}
