<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weekly_digest' => ['sometimes', 'boolean'],
            'status_updates' => ['sometimes', 'boolean'],
            'interview_reminders' => ['sometimes', 'boolean'],
            'messages' => ['sometimes', 'boolean'],
            'marketing' => ['sometimes', 'boolean'],
        ];
    }
}
