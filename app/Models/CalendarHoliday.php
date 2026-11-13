<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'title',
        'type',
        'created_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date:Y-m-d',
            'verified_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
