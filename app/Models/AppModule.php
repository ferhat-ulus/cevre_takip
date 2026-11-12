<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppModule extends Model
{
    public const ROLE_ADVISOR = 'advisor';
    public const ROLE_VIEWER = 'viewer';

    protected $table = 'modules';

    protected $fillable = ['key', 'name', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function userAccesses(): HasMany
    {
        return $this->hasMany(ModuleUserAccess::class, 'module_id');
    }
}
