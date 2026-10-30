<?php

namespace App\Services\Ats;

use App\Jobs\RunAtsEvaluation;
use App\Models\Application;
use App\Models\CandidateCv;
use App\Models\CvMatchResult;
use Illuminate\Support\Str;

/**
 * نقطة دخول واحدة لطلب تقييم: بتستخدمها الـ API وبتستخدمها (اختياريًا) عملية التقديم نفسها.
 */
class AtsEvaluationDispatcher
{
    /** نعتبر الـ pending "عالق" بعد المدة دي، ونسمح بطلب جديد */
    private const STALE_AFTER_MINUTES = 15;

    public function pendingFor(Application $application): ?CvMatchResult
    {
        return CvMatchResult::where('application_id', $application->id)
            ->where('run_status', CvMatchResult::PENDING)
            ->where('created_at', '>', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->latest('id')
            ->first();
    }

    /** null لو مفيش CV صالح للتقييم */
    public function dispatchFor(Application $application, ?int $userId = null): ?CvMatchResult
    {
        // الـ CV ممكن يكون اتحذف (soft delete) بعد التقديم، والملف لسه موجود
        $cvId = $application->cv_id;
        if (! $cvId || ! CandidateCv::withTrashed()->whereKey($cvId)->exists()) {
            return null;
        }

        $result = CvMatchResult::create([
            'application_id' => $application->id,
            'cv_id' => $cvId,
            'run_status' => CvMatchResult::PENDING,
            'request_id' => (string) Str::uuid(),
            'UID' => $userId,
            'LUID' => $userId,
        ]);

        RunAtsEvaluation::dispatch($result->id)->afterCommit();

        return $result;
    }
}
