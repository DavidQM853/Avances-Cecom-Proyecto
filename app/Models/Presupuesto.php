<?php

namespace App\Models;

use App\Enums\EstadoPresupuesto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Presupuesto extends Model
{
    use HasFactory;

    protected $fillable = [
        'departamento_id',
        'periodo_fiscal_id',
        'monto_total',
        'monto_ejercido',
        'monto_comprometido',
        'estado',
        'creado_por',
        'aprobado_por',
        'aprobado_en',
        'notas',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'monto_ejercido' => 'decimal:2',
        'monto_comprometido' => 'decimal:2',
        'monto_disponible' => 'decimal:2',
        'estado' => EstadoPresupuesto::class,
        'aprobado_en' => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function periodoFiscal(): BelongsTo
    {
        return $this->belongsTo(PeriodoFiscal::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaPresupuestal::class);
    }

    /**
     * Gastos operativos a través de las partidas.
     */
    public function gastos(): HasManyThrough
    {
        return $this->hasManyThrough(
            GastoOperativo::class,
            PartidaPresupuestal::class,
            'presupuesto_id',   // FK en partidas_presupuestales
            'partida_id',       // FK en gastos_operativos
        );
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeDelDepartamento($query, int $departamentoId)
    {
        return $query->where('departamento_id', $departamentoId);
    }

    public function scopeDelPeriodo($query, int $periodoFiscalId)
    {
        return $query->where('periodo_fiscal_id', $periodoFiscalId);
    }

    public function scopeAprobados($query)
    {
        return $query->where('estado', EstadoPresupuesto::APROBADO);
    }

    public function scopeActivos($query)
    {
        return $query->whereIn('estado', [
            EstadoPresupuesto::APROBADO,
            EstadoPresupuesto::EJECUTANDO,
        ]);
    }

    // ─── Métodos de negocio ────────────────────────────────

    /**
     * Calcula el porcentaje de ejecución del presupuesto.
     */
    public function porcentajeEjecucion(): float
    {
        if ($this->monto_total <= 0) {
            return 0;
        }

        return round(($this->monto_ejercido / $this->monto_total) * 100, 2);
    }

    /**
     * Verifica si el presupuesto tiene fondos disponibles para un monto dado.
     */
    public function tieneFondos(float $monto): bool
    {
        return $this->monto_disponible >= $monto;
    }
}
