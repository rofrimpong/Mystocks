<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_id',
        'user_id',
        'device_id',
        'operation_type',
        'idempotency_key',
        'status',
        'payload',
        'server_result',
        'conflict_reason',
        'retry_count',
        'client_created_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'server_result' => 'array',
            'client_created_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
