<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirmLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'firm_id',
        'user_id',
        'action',
        'description',
        'from_consultant',
        'to_consultant',
        'reason',
        'history_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}