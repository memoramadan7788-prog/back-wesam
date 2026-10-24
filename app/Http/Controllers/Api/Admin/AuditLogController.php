<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-logs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = AuditLog::with('user')
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('auditable_type'), fn ($q, $type) => $q->where('auditable_type', 'like', "%{$type}%"))
            ->when($request->query('user_id'), fn ($q, $uid) => $q->where('user_id', $uid))
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return AuditLogResource::collection($logs);
    }
}

