<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Requests\Candidate\UploadProfilePhotoRequest;
use App\Http\Resources\CandidateProfileResource;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CandidateProfileController extends Controller
{
    /**
     * GET /api/candidate/profile
     */
    public function show(Request $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        return (new CandidateProfileResource(
            $profile->load(['user', 'skills', 'candidateExperiences', 'candidateEducations', 'candidateCvs'])
        ))->response()->setStatusCode(200);
    }

    /**
     * PUT /api/candidate/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        $profile->update($request->validated());

        return (new CandidateProfileResource(
            $profile->load(['user', 'skills', 'candidateExperiences', 'candidateEducations', 'candidateCvs'])
        ))->response()->setStatusCode(200);
    }

    /**
     * POST /api/candidate/profile/photo
     */
    public function uploadPhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        if ($profile->photo_path) {
            Storage::disk('public')->delete($profile->photo_path);
        }

        $path = $request->file('photo')->store("candidate-photos/{$request->user()->id}", 'public');
        $profile->update(['photo_path' => $path]);

        return (new CandidateProfileResource(
            $profile->fresh()->load('user')
        ))->response()->setStatusCode(201);
    }

    /**
     * DELETE /api/candidate/profile/photo
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $profile = CandidateProfile::where('user_id', $request->user()->id)->firstOrFail();

        if ($profile->photo_path) {
            Storage::disk('public')->delete($profile->photo_path);
            $profile->update(['photo_path' => null]);
        }

        return response()->json(['message' => 'Profile photo deleted successfully.'], 200);
    }

    /**
     * GET /api/candidate/notifications
     */
    public function notifications(Request $request): JsonResponse
    {
        $query = $request->user()->receivedNotifications()->latest();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $perPage = (int) $request->input('per_page', 15);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $notifications->items(),
            'meta'   => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
                'unread_count' => $request->user()->receivedNotifications()->whereNull('read_at')->count(),
            ],
        ], 200);
    }

    /**
     * PATCH /api/candidate/notifications/{id}/read
     */
    public function markNotificationAsRead(Request $request, $id): JsonResponse
    {
        $notification = $request->user()->receivedNotifications()->findOrFail($id);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'message'      => 'Notification marked as read.',
            'notification' => $notification,
        ], 200);
    }

    /**
     * PATCH /api/candidate/notifications/read-all
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        $updatedCount = $request->user()->receivedNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message'       => 'All notifications marked as read.',
            'updated_count' => $updatedCount,
        ], 200);
    }
}
