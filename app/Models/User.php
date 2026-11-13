<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'role',
        'active',
        'must_change',
        'sess_ver',
        'cal_token',
        'can_be_consultant',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'active' => 'boolean',
            'must_change' => 'boolean',
            'can_be_consultant' => 'boolean',
        ];
    }

    public function scopeConsultantCapable(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('role', 'employee')
                ->orWhere('can_be_consultant', true);
        });
    }

    public function scopeActiveConsultants(Builder $query): Builder
    {
        return $query->consultantCapable()->where('active', true);
    }

    public function isConsultantCapable(): bool
    {
        return $this->role === 'employee' || $this->can_be_consultant;
    }

    public function moduleAccesses(): HasMany
    {
        return $this->hasMany(ModuleUserAccess::class, 'user_id');
    }
}
