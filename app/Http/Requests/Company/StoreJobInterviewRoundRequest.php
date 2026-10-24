<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobInterviewRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jobParam = $this->route('job');
        $jobId = is_object($jobParam) ? $jobParam->id : $jobParam;

        return [
            'round_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('job_interview_rounds', 'round_no')
                    ->where(fn ($query) => $query->where('job_id', $jobId)->whereNull('deleted_at')),
            ],
            'interview_type' => ['required', 'string', 'in:onsite,online,custom'],
            'duration_min'   => ['required', 'integer', 'min:15', 'max:480'],
        ];
    }
}

