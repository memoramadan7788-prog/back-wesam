<?php

namespace App\Http\Requests;

use App\Models\InterviewerAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAvailabilityRequest extends FormRequest
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
            'start_at' => ['sometimes', 'required', 'date', 'after:now'],
            'end_at' => ['sometimes', 'required', 'date'],
            'availability_status' => ['sometimes', 'required', 'string', 'in:open,cancelled'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $slotId = $this->route('id');
            /** @var InterviewerAvailability|null $slot */
            $slot = InterviewerAvailability::query()->find($slotId);

            if (!$slot) {
                return;
            }

            // Cannot update timings on booked slot to protect confirmed interviews
            if (strtolower((string) $slot->availability_status) === 'booked') {
                if ($this->has('start_at') || $this->has('end_at')) {
                    $validator->errors()->add('availability_status', 'Cannot update time slots for an availability record that is already booked.');
                    return;
                }
            }

            $startAt = $this->has('start_at') ? Carbon::parse($this->input('start_at')) : $slot->start_at;
            $endAt = $this->has('end_at') ? Carbon::parse($this->input('end_at')) : $slot->end_at;

            if ($endAt->lessThanOrEqualTo($startAt)) {
                $validator->errors()->add('end_at', 'The end time must be after the start time.');
                return;
            }

            // Prevent overlap with another slot for this interviewer
            $hasOverlap = InterviewerAvailability::query()
                ->where('interviewer_id', $slot->interviewer_id)
                ->where('id', '!=', $slot->id)
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
