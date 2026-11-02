<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $guarded = [];

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
}
