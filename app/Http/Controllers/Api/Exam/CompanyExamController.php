<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\StoreExamRequest;
use App\Http\Requests\Exam\StoreQuestionRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\QuestionResource;
use App\Models\Company;
use App\Models\Exam;
use App\Models\Job;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyExamController extends Controller
{
    /**
     * POST /api/company/exams
     *
     * company_id is auto-resolved from the authenticated user (Phase 0.1).
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $companyId = $user->resolveCompanyId();

        if (! $companyId) {
            return response()->json([
                'message' => 'You are not associated with any company.',
            ], 403);
        }

        $validated['company_id'] = $companyId;
        $validated['created_by'] = $user->id;
        $validated['status'] = 'published';

        $exam = Exam::create($validated);

        return response()->json([
            'message' => 'Exam created successfully.',
            'exam'    => new ExamResource($exam),
        ], 201);
    }

    /**
     * POST /api/company/exams/{id}/questions
     */
    public function addQuestion(StoreQuestionRequest $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $validated = $request->validated();

        $options = $validated['options'] ?? [];
        unset($validated['options']);

        $validated['exam_id'] = $exam->id;
        $validated['order_no'] = $validated['order_no'] ?? (Question::where('exam_id', $exam->id)->count() + 1);
        $validated['status'] = 'published';

        $question = DB::transaction(function () use ($validated, $options) {
            $q = Question::create($validated);

            foreach ($options as $opt) {
                QuestionOption::create([
                    'question_id' => $q->id,
                    'option_text' => $opt['option_text'] ?? $opt['text'] ?? '',
                    'is_correct'  => $opt['is_correct'] ?? false,
                ]);
            }

            return $q;
        });

        return response()->json([
            'message'  => 'Question added successfully.',
            'question' => new QuestionResource($question->load('options')),
        ], 201);
    }

    /**
     * POST /api/company/jobs/{jobId}/exams/{examId}/attach
     */
    public function attachToJob(Request $request, int $jobId, int $examId): JsonResponse
    {
        $validated = $request->validate([
            'stage_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
        ]);

        $job = Job::findOrFail($jobId);
        $exam = Exam::findOrFail($examId);

        $user = $request->user();
        if ($user && $user->account_type !== 'admin') {
            $companyId = $user->resolveCompanyId();
            if ((int) $job->company_id !== (int) $companyId || (int) $exam->company_id !== (int) $companyId) {
                abort(403, 'You do not have permission to attach exams for this company.');
            }
        }

        $stageOrder = $validated['stage_order'] ?? null;
        if ($stageOrder === null) {
            $maxOrder = DB::table('job_exams')->where('job_id', $job->id)->max('stage_order') ?? 0;
            $stageOrder = (int) $maxOrder + 1;
        }

        DB::table('job_exams')->updateOrInsert([
            'job_id'  => $job->id,
            'exam_id' => $exam->id,
        ], [
            'stage_order' => (int) $stageOrder,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'message'     => 'Exam attached to job successfully.',
            'job_id'      => $job->id,
            'exam_id'     => $exam->id,
            'stage_order' => (int) $stageOrder,
        ], 200);
    }
}
