<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REWARDED = 'rewarded';

    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'referral_code',
        'status',
        'qualified_at',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function isQualified(): bool
    {
        return in_array(
            $this->status,
            [self::STATUS_QUALIFIED, self::STATUS_REWARDED],
            true
        );
    }

    public function isRewarded(): bool
    {
        return $this->status === self::STATUS_REWARDED;
    }
}
