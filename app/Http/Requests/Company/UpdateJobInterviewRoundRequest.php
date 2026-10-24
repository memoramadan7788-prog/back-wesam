<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobInterviewRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jobParam = $this->route('job');
        $jobId = is_object($jobParam) ? $jobParam->id : $jobParam;

        $roundParam = $this->route('interview_round') ?? $this->route('round');
        $roundId = is_object($roundParam) ? $roundParam->id : $roundParam;

        return [
            'round_no' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::unique('job_interview_rounds', 'round_no')
                    ->where(fn ($query) => $query->where('job_id', $jobId)->whereNull('deleted_at'))
                    ->ignore($roundId),
            ],
            'interview_type' => ['sometimes', 'string', 'in:onsite,online,custom'],
            'duration_min'   => ['sometimes', 'integer', 'min:15', 'max:480'],
        ];
    }
}

