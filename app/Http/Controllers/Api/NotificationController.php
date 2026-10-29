<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Admin;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /api/notifications */
    public function index(Request $request): JsonResponse
    {
        $query = $this->ownedNotifications($request->user())->latest();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $notifications = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->ownedNotifications($request->user())->whereNull('read_at')->count(),
            ],
        ]);
    }

    /** PATCH /api/notifications/{id}/read */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->ownedNotifications($request->user())->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    /** PATCH /api/notifications/read-all */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updatedCount = $this->ownedNotifications($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'data' => ['updated_count' => $updatedCount],
        ]);
    }

    private function ownedNotifications(object $principal)
    {
        return Notification::query()->when(
            $principal instanceof Admin,
            fn ($query) => $query->where('admin_recipient_id', $principal->getKey()),
            fn ($query) => $query->where('recipient_id', $principal->getKey())
        );
    }
}
