<?php

namespace App\Http\Controllers\Api\Salary;

use App\Http\Controllers\Controller;
use App\Models\SalaryRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryBenchmarkController extends Controller
{
    /**
     * GET /api/salary-benchmark
     */
    public function index(Request $request): JsonResponse
    {
        $jobTitle = $request->query('job_title');
        $categoryId = $request->query('category_id');

        $query = SalaryRecord::query()
            ->when($jobTitle, fn ($q) => $q->where('job_title', 'like', "%{$jobTitle}%"))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId));

        $count = $query->count();
        $avgMin = (float) ($query->avg('amount_min') ?? 0);
        $avgMax = (float) ($query->avg('amount_max') ?? 0);
        $records = $query->limit(20)->get();

        return response()->json([
            'market_summary' => [
                'total_records'   => $count,
                'average_minimum' => round($avgMin, 2),
                'average_maximum' => round($avgMax, 2),
                'median_range'    => round(($avgMin + $avgMax) / 2, 2),
                'currency'        => 'EGP',
            ],
            'records'        => $records,
        ], 200);
    }
}

