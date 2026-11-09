<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeloPredictivo extends Model
{
    use HasFactory;

    protected $table = 'modelos_predictivos';

    protected $fillable = [
        'version',
        'version_datos',
        'entrenamiento_desde',
        'entrenamiento_hasta',
        'validacion_desde',
        'validacion_hasta',
        'metricas_validacion',
        'referencia_artefacto',
    ];

    protected $casts = [
        'entrenamiento_desde' => 'date',
        'entrenamiento_hasta' => 'date',
        'validacion_desde' => 'date',
        'validacion_hasta' => 'date',
        'metricas_validacion' => 'array',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function proyecciones(): HasMany
    {
        return $this->hasMany(ProyeccionAnual::class, 'modelo_id');
    }
}
