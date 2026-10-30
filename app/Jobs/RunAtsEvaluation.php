<?php

namespace App\Jobs;

use App\Models\CandidateCv;
use App\Models\CvMatchResult;
use App\Services\Ats\AtsEvaluator;
use App\Services\Ats\CvExtractionException;
use App\Services\Ats\CvTextExtractor;
use App\Services\Ats\InvalidModelResponseException;
use App\Services\Ats\ModelNotAllowedException;
use App\Services\Ats\OllamaUnavailableException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunAtsEvaluation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120];
    public int $timeout;

    public function __construct(public int $matchResultId)
    {
        $this->timeout = (int) config('ats.ollama.timeout', 120) + 30;
    }

    public function handle(AtsEvaluator $evaluator): void
    {
        $result = CvMatchResult::with(['application.job.skills'])->find($this->matchResultId);

        if (! $result || $result->run_status !== CvMatchResult::PENDING) {
            return;
        }

        try {
            $job = $result->application->job;

            $description = trim((string) $job->description);
            if ($description === '') {
                $description = trim((string) $job->title);
            }
            if ($description === '') {
                $this->fail_($result, 'The job has no description or title to evaluate the CV against.');
                return;
            }

            $cv = CandidateCv::withTrashed()->find($result->cv_id);
            $disk = Storage::disk('local');
            if (! $cv || ! $disk->exists($cv->storage_path)) {
                $this->fail_($result, 'The CV file was not found in storage.');
                return;
            }

            $extracted = CvTextExtractor::fromConfig()->fromFile(
                $disk->path($cv->storage_path),
                
                pathinfo((string) $cv->original_filename, PATHINFO_EXTENSION) !== ''
                    ? $cv->original_filename
                    : $cv->storage_path,
            );

            $out = $evaluator->evaluate(
                $extracted,
                $description,
                $job->title,
                $this->requiredSkills($job),
            );

            $this->store($result, $out);
        } catch (CvExtractionException | InvalidModelResponseException | ModelNotAllowedException $e) {
            
            $this->fail_($result, $e->getMessage());
        }
        
    }

  
    public function failed(Throwable $e): void
    {
        $message = $e instanceof OllamaUnavailableException
            ? 'The analysis service is currently unavailable. Please try again later.'
            : 'An unexpected error occurred during analysis.';

        if (! $e instanceof OllamaUnavailableException) {
            Log::error('ATS evaluation crashed', ['match_result_id' => $this->matchResultId, 'error' => $e->getMessage()]);
        }

        CvMatchResult::whereKey($this->matchResultId)
            ->where('run_status', CvMatchResult::PENDING)
            ->update([
                'run_status' => CvMatchResult::FAILED,
                'error_message' => $message,
                'finished_at' => now(),
            ]);
    }

    
    private function requiredSkills($job): ?array
    {
        $all = $job->skills;
        $required = $all->filter(fn ($s) => (bool) ($s->pivot->is_required ?? false));
        $names = ($required->isNotEmpty() ? $required : $all)->pluck('name')->values()->all();

        return $names === [] ? null : $names;
    }

    private function store(CvMatchResult $result, array $out): void
    {
        $result->update([
            'score' => $out['score'],
            'matched_skills' => $out['matched_skills'],
            'missing_skills' => $out['missing_skills'],
            'summary' => $out['summary'],
            'model_version' => $out['model_version'],
            'run_status' => CvMatchResult::OK,
            'error_message' => null,
            'finished_at' => now(),
            'details' => [
                'recommendation' => $out['recommendation'],
                'score_breakdown' => $out['score_breakdown'],
                'experience_match' => $out['experience_match'],
                'education_match' => $out['education_match'],
                'strengths' => $out['strengths'],
                'dropped_unverified_strengths' => $out['dropped_unverified_strengths'],
                'weaknesses' => $out['weaknesses'],
                'unverified_fields' => $out['unverified_fields'],
                'skills_source' => $out['skills_source'],
                'cv_truncated' => $out['cv_truncated'],
                'attempts' => $out['attempts'],
            ],
        ]);
    }

    private function fail_(CvMatchResult $result, string $message): void
    {
        $result->update([
            'run_status' => CvMatchResult::FAILED,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}