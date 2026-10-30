<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Admin;
use App\Models\User;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'admin_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an audit log entry.
     */
    public static function record(
        string $action,
        Model $subject,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?int $adminId = null
    ): self {
        $authenticated = auth()->user();
        $resolvedUserId = $userId;

        // Admin actions are tracked via admin_id. user_id has a FK to users,
        // so it must be NULL (never 0) when the actor is an Admin.
        if ($adminId !== null || $resolvedUserId === 0) {
            $resolvedUserId = null;
        }

        if ($resolvedUserId === null && $adminId === null && $authenticated instanceof User) {
            $resolvedUserId = $authenticated->id;
        }

        return self::create([
            'user_id'        => $resolvedUserId,
            'admin_id'       => $adminId,
            'action'         => $action,
            'auditable_type' => get_class($subject),
            'auditable_id'   => $subject->getKey(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'reason'         => $reason,
            'ip_address'     => $ipAddress ?? request()?->ip(),
        ]);
    }
}