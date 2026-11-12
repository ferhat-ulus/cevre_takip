<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleUserAccess extends Model
{
    protected $table = 'module_user_accesses';

    protected $fillable = ['user_id', 'module_id', 'role'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AppModule::class, 'module_id');
    }
}
