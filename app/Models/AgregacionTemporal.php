<?php

namespace App\Models;

use App\Enums\PeriodoTipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgregacionTemporal extends Model
{
    protected $table = 'agregaciones_temporales';

    protected $fillable = [
        'departamento_id',
        'categoria_opex_id',
        'periodo_tipo',
        'fecha_inicio',
        'fecha_fin',
        'monto_total',
        'monto_promedio',
        'variacion_porcentual',
        'cantidad_transacciones',
        'desglose',
    ];

    protected $casts = [
        'periodo_tipo' => PeriodoTipo::class,
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'monto_total' => 'decimal:2',
        'monto_promedio' => 'decimal:2',
        'variacion_porcentual' => 'decimal:4',
        'cantidad_transacciones' => 'integer',
        'desglose' => 'array',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function categoriaOpex(): BelongsTo
    {
        return $this->belongsTo(CategoriaOpex::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeDePeriodo($query, PeriodoTipo $tipo)
    {
        return $query->where('periodo_tipo', $tipo);
    }

    public function scopeDelDepartamento($query, int $departamentoId)
    {
        return $query->where('departamento_id', $departamentoId);
    }

    public function scopeEntreFechas($query, string $desde, string $hasta)
    {
        return $query->where('fecha_inicio', '>=', $desde)
                     ->where('fecha_fin', '<=', $hasta);
    }
}
