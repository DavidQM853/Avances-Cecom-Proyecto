<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraEvento extends Model
{
    use HasFactory;

    protected $table = 'bitacora_eventos';

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'entidad_tipo',
        'entidad_id',
        'accion',
        'motivo',
        'valores_antes',
        'valores_despues',
        'ocurrido_en',
    ];

    protected $casts = [
        'valores_antes' => 'array',
        'valores_despues' => 'array',
        'ocurrido_en' => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
