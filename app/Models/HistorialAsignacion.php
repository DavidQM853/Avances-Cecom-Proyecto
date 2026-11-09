<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialAsignacion extends Model
{
    protected $table = 'historial_asignaciones';

    protected $fillable = [
        'user_id',
        'rol_id',
        'accion',
        'datos_anteriores',
        'datos_nuevos',
        'motivo',
        'ejecutado_por',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }
}
