<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     * Note: Evaluator ID is derived strictly from the authenticated user.
     * feedback_shared_at is strictly excluded from client submission to prevent premature feedback exposure.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numeric_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal_notes' => ['required', 'string', 'min:3', 'max:10000'],
            'candidate_feedback' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
