<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Business extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'registration_number',
        'phone',
        'email',
        'address',
        'city',
        'region',
        'country',
        'currency',
        'timezone',
        'logo_path',
        'status',
        'plan',
        'allow_negative_stock',
        'multi_branch_enabled',
        'settings',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_negative_stock' => 'boolean',
            'multi_branch_enabled' => 'boolean',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot(['branch_id', 'is_owner', 'role', 'is_active'])
            ->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('is_owner', true);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial'], true);
    }
    public function planLimits(): array
    {
        $plan = DB::table('plans')
            ->where('slug', $this->plan ?? 'free')
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return [
                'branches' => 1,
                'users' => 1,
                'products' => 50,
            ];
        }

        return [
            'branches' => $plan->max_branches !== null ? (int) $plan->max_branches : null,
            'users' => $plan->max_users !== null ? (int) $plan->max_users : null,
            'products' => $plan->max_products !== null ? (int) $plan->max_products : null,
        ];
    }


    public function branchLimit(): ?int
    {
        return $this->planLimits()['branches'];
    }

    public function userLimit(): ?int
    {
        return $this->planLimits()['users'];
    }

    public function productLimit(): ?int
    {
        return $this->planLimits()['products'];
    }
}
