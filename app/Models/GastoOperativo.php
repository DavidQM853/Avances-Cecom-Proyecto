<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoOperativo extends Model
{
    use HasFactory;

    protected $table = 'gastos_operativos';

    protected $fillable = [
        'partida_id',
        'registrado_por',
        'compromiso_id',
        'monto',
        'concepto',
        'referencia_documento',
        'fecha_gasto',
        'fecha_registro',
        'metadata',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_gasto' => 'date',
        'fecha_registro' => 'date',
        'metadata' => 'array',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function partida(): BelongsTo
    {
        return $this->belongsTo(PartidaPresupuestal::class, 'partida_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function compromiso(): BelongsTo
    {
        return $this->belongsTo(CompromisoReserva::class, 'compromiso_id');
    }

    // ─── Scopes ────────────────────────────────────────────

    /**
     * Filtra gastos dentro de un rango de fechas.
     */
    public function scopeEntreFechas($query, string $desde, string $hasta)
    {
        return $query->whereBetween('fecha_gasto', [$desde, $hasta]);
    }

    /**
     * Filtra gastos de un departamento específico (a través de partida → presupuesto).
     */
    public function scopeDelDepartamento($query, int $departamentoId)
    {
        return $query->whereHas('partida.presupuesto', function ($q) use ($departamentoId) {
            $q->where('departamento_id', $departamentoId);
        });
    }

    /**
     * Filtra gastos de una categoría OPEX específica.
     */
    public function scopeDeCategoria($query, int $categoriaOpexId)
    {
        return $query->whereHas('partida', function ($q) use ($categoriaOpexId) {
            $q->where('categoria_opex_id', $categoriaOpexId);
        });
    }
}
