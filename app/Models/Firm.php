<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Firm extends Model {
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    public $timestamps = false; // Bu satır çok önemli
    protected $casts = ['data' => 'array'];
}