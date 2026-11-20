<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitApprovalRequest extends Model
{
    protected $fillable = [
        'firm_id', 'assigned_user_id', 'requested_by_user_id', 'visit_date', 'title', 'notes', 'status',
        'reviewed_by_user_id', 'visit_id', 'reviewed_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
