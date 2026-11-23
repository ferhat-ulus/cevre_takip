<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ministry_entered_at' => 'datetime',
        'ministry_transfer_snapshot' => 'array',
    ];

    // Ziyaretin bağlı olduğu danışman (Kullanıcı)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Ziyaretin yapıldığı tesis (Firma)
    public function firm()
    {
        return $this->belongsTo(Firm::class, 'firm_id', 'id');
    }

    public function ministryEnteredBy()
    {
        return $this->belongsTo(User::class, 'ministry_entered_by_user_id');
    }
}
