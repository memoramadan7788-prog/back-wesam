<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\SubmitExamAnswerRequest;
use App\Http\Resources\ExamAttemptResource;
use App\Http\Resources\QuestionResource;
use App\Models\Answer;
use App\Models\Application;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\GradingResult;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CandidateExamController extends Controller
{
    /**
     * POST /api/candidate/exams/{examId}/applications/{applicationId}/start
     */
    public function start(Request $request, int $examId, int $applicationId): JsonResponse
    {
        $exam = Exam::with(['questions.options'])->findOrFail($examId);
        $application = Application::findOrFail($applicationId);

        $attemptCount = ExamAttempt::where('exam_id', $exam->id)
            ->where('application_id', $application->id)
            ->count();

        if ($attemptCount >= $exam->attempt_limit) {
            return response()->json([
                'message' => "Maximum trial attempts ({$exam->attempt_limit} attempts) reached for this exam.",
            ], 403);
        }

        $activeAttempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('application_id', $application->id)
            ->where('status', 'in_progress')
            ->first();

        if (! $activeAttempt) {
            $activeAttempt = ExamAttempt::create([
                'exam_id'        => $exam->id,
                'application_id' => $application->id,
                'attempt_no'     => $attemptCount + 1,
                'status'         => 'in_progress',
                'started_at'     => now(),
            ]);
        }

        $request->attributes->set('is_candidate_exam', true);

        return response()->json([
            'attempt'   => new ExamAttemptResource($activeAttempt->load('exam')),
            'questions' => QuestionResource::collection($exam->questions),
        ], 200);
    }

    /**
     * POST /api/candidate/exam-attempts/{attemptId}/submit
     */
    public function submit(SubmitExamAnswerRequest $request, int $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::with('exam.questions.options')->findOrFail($attemptId);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'message' => 'This exam attempt has already been submitted.',
            ], 409);
        }

        $validated = $request->validated();
        $answersData = $validated['answers'];

        $result = DB::transaction(function () use ($attempt, $answersData) {
            $totalScore = 0;

            foreach ($answersData as $ans) {
                $question = Question::with('options')->find($ans['question_id']);
                if (! $question) {
                    continue;
                }

                $score = 0;
                $selectedOptionId = $ans['selected_option_id'] ?? null;

                if ($question->question_type === 'mcq' && $selectedOptionId) {
                    $selected = QuestionOption::where('question_id', $question->id)
                        ->where('id', $selectedOptionId)
                        ->first();

                    if ($selected && $selected->is_correct) {
                        $score = $question->points;
                    }
                }

                Answer::updateOrInsert(
                    [
                        'attempt_id'  => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'selected_option_id' => $selectedOptionId,
                        'answer_text'        => $ans['answer_text'] ?? null,
                        'code'               => $ans['code'] ?? null,
                        'score'              => $score,
                        'updated_at'         => now(),
                        'created_at'         => now(),
                    ]
                );

                $totalScore += $score;
            }

            $attempt->update([
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]);

            $grading = GradingResult::create([
                'attempt_id'     => $attempt->id,
                'total_score'    => $totalScore,
                'grading_method' => 'auto',
                'status'         => 'completed',
            ]);

            return ['attempt' => $attempt, 'grading' => $grading];
        });

        return response()->json([
            'message'        => 'Exam attempt submitted and graded successfully.',
            'attempt_status' => 'submitted',
            'total_score'    => (float) $result['grading']->total_score,
            'passing_score'  => (float) $attempt->exam->passing_score,
            'is_passed'      => (float) $result['grading']->total_score >= (float) $attempt->exam->passing_score,
        ], 200);
    }

    /**
     * GET /api/candidate/exam-attempts/{attemptId}/result
     */
    public function result(Request $request, int $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::with(['exam', 'gradingResults'])->findOrFail($attemptId);
        $grading = $attempt->gradingResults->first();

        return response()->json([
            'attempt'        => new ExamAttemptResource($attempt),
            'total_score'    => $grading ? (float) $grading->total_score : 0,
            'passing_score'  => (float) $attempt->exam->passing_score,
            'is_passed'      => $grading ? ((float) $grading->total_score >= (float) $attempt->exam->passing_score) : false,
        ], 200);
    }
}

