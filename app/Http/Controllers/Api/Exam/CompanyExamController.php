<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\StoreExamRequest;
use App\Http\Requests\Exam\StoreQuestionRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\QuestionResource;
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
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;
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
        $job = Job::findOrFail($jobId);
        $exam = Exam::findOrFail($examId);

        DB::table('job_exams')->updateOrInsert([
            'job_id'  => $job->id,
            'exam_id' => $exam->id,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Exam attached to job successfully.',
        ], 200);
    }
}

