<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\JobScreeningQuestionResource;
use App\Models\Job;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobScreeningQuestionController
{
    /** GET /api/jobs/{job}/screening-questions */
    public function index(int $job): AnonymousResourceCollection
    {
        $jobModel = Job::available()->findOrFail($job);

        return JobScreeningQuestionResource::collection($jobModel->screeningQuestions()->get());
    }
}
