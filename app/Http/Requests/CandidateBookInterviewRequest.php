<?php

namespace App\Http\Requests;

use App\Enums\InterviewType;
use Illuminate\Foundation\Http\FormRequest;

class CandidateBookInterviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('interview_type') && is_string($this->interview_type)) {
            $normalized = strtolower(trim($this->interview_type));
            if ($normalized === 'onsite') {
                $this->merge([
                    'interview_type' => InterviewType::ON_SITE->value,
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer'],
            'availability_id' => ['required', 'integer'],
            'interview_type' => ['nullable', 'string', 'in:' . implode(',', InterviewType::values())],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
