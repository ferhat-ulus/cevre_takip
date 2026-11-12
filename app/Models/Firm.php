<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Firm extends Model {
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    public $timestamps = false; // Bu satır çok önemli
    protected $casts = ['data' => 'array'];

    protected static function booted(): void
    {
        // Eski kayıtlar yalnızca geçiş dönemi için okunabilir kalır. Yeni
        // kayıt akışları danışman adını JSON'a hiç yazmaz.
        static::creating(function (self $firm): void {
            if ($firm->consultant_user_id || empty($firm->data['consultant'])) {
                return;
            }

            $matches = User::query()->where('role', 'employee')
                ->where('name', $firm->data['consultant'])->pluck('id');
            if ($matches->count() === 1) {
                $firm->consultant_user_id = $matches->first();
            }
        });
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role === 'admin'
            ? $query
            : $query->where('consultant_user_id', $user->id);
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user !== null && (int) $this->consultant_user_id === (int) $user->id;
    }

    public function consultantName(): string
    {
        return $this->consultant?->name ?? ($this->data['consultant'] ?? 'Atanmamış');
    }
}
