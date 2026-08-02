<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name', 'email', 'password', 'branch_id', 'phone', 'is_active',
        'locale', 'theme_preference', 'avatar_path',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Deactivating a user must lock them out immediately, not at next password change. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /** Whether landed cost, supplier pricing and margin are visible to this user. */
    public function canSeeCost(): bool
    {
        return $this->can('view_cost');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
