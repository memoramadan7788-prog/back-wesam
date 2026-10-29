<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\Admin;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    /** POST /api/contact. Guests must identify themselves; registered users are resolved from users. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $baseRules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
        ];
        $identityRules = $user
            ? ['name' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255']]
            : ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']];
        $data = $request->validate(array_merge($baseRules, $identityRules));

        $message = ContactMessage::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'name' => $user?->name ?? $data['name'],
            'email' => $user?->email ?? $data['email'],
            'user_id' => $user instanceof \App\Models\User ? $user->id : null,
            'admin_id' => $user instanceof Admin ? $user->id : null,
            'status' => 'new',
        ]);

        return response()->json([
            'message' => 'Your contact message has been submitted successfully.',
            'data' => new ContactMessageResource($message),
        ], 201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $messages = ContactMessage::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('email'), fn ($q) => $q->where('email', $request->input('email')))
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return ContactMessageResource::collection($messages);
    }

    public function show(int $id): ContactMessageResource
    {
        return new ContactMessageResource(ContactMessage::findOrFail($id));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'resolved', 'closed'])],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $message = ContactMessage::findOrFail($id);
        $message->update($data);

        return response()->json([
            'message' => 'Contact message status updated successfully.',
            'data' => new ContactMessageResource($message->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        ContactMessage::findOrFail($id)->delete();
        return response()->json(['message' => 'Contact message deleted successfully.']);
    }
}
