<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\JobCategory;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * GET /api/job-categories
     * List all active job categories for dropdowns.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = JobCategory::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'data' => $categories,
        ], 200);
    }

    /**
     * GET /api/skills
     * List all skills with optional search.
     */
    public function skills(Request $request): JsonResponse
    {
        $query = Skill::where('accepted', true);

        if ($search = $request->query('q')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $skills = $query->orderBy('name')->get(['id', 'name', 'type']);

        return response()->json([
            'data' => $skills,
        ], 200);
    }

    /**
     * GET /api/countries
     * List all countries for location dropdowns.
     */
    public function countries(Request $request): JsonResponse
    {
        $countries = Country::orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'data' => $countries,
        ], 200);
    }
}

