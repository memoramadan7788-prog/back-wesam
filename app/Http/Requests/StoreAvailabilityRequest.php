<?php

namespace App\Http\Requests;

use App\Models\InterviewerAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAvailabilityRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interviewer_id' => ['nullable', 'integer'],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ];
    }

    /**
     * Configure the validator instance to prevent overlapping availability records.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $interviewerId = $this->input('interviewer_id') ?: $this->user()->id;
            $startAt = Carbon::parse($this->input('start_at'));
            $endAt = Carbon::parse($this->input('end_at'));

            $hasOverlap = InterviewerAvailability::query()
                ->where('interviewer_id', $interviewerId)
                ->where('availability_status', '!=', 'cancelled')
                ->where(function ($q) use ($startAt, $endAt) {
                    $q->where('start_at', '<', $endAt)
                      ->where('end_at', '>', $startAt);
                })
                ->exists();

            if ($hasOverlap) {
                $validator->errors()->add('start_at', 'This availability slot overlaps with an existing slot for this interviewer.');
            }
        });
    }
}
