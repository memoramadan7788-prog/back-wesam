<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
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
        ?string $ipAddress = null
    ): self {
        return self::create([
            'user_id'        => $userId ?? auth()->id(),
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

