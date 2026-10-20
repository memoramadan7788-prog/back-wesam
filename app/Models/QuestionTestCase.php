<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTestCase extends Model
{
    protected $table = 'question_test_cases';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_hidden' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
