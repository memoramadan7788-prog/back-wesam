<?php

namespace App\Http\Requests;

use App\Enums\InterviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleInterviewRequest extends FormRequest
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
     * Normalizes the 'onsite' compatibility alias to the canonical 'on_site' enum value.
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
     * Note: application_id and interviewer bookings are required per ERD Page 4.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer'],
            'round_no' => ['nullable', 'integer', 'min:1'],
            'interview_type' => ['required', 'string', Rule::in(InterviewType::values())],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'bookings' => ['required', 'array', 'min:1'],
            'bookings.*.interviewer_id' => ['required', 'integer'],
            'bookings.*.availability_id' => ['required', 'integer'],
        ];
    }
}
