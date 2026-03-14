<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReunionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['reunion_id', 'user_id', 'accion', 'metadata', 'observacion', 'created_at'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Agregando esta relación con user:
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function ($log) {
            $log->created_at = now();
        });
    }
}
