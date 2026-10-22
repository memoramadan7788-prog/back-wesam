<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_type'        => ['required', Rule::in(['mcq', 'written', 'coding'])],
            'content'              => ['required', 'string'],
            'points'               => ['required', 'integer', 'min:1'],
            'order_no'             => ['nullable', 'integer', 'min:1'],
            'options'              => ['nullable', 'array', 'min:2'],
            'options.*.text'        => ['nullable', 'string'],
            'options.*.option_text' => ['nullable', 'string'],
            'options.*.is_correct'  => ['required_with:options', 'boolean'],
        ];
    }
}

