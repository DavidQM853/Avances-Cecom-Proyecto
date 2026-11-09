<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartidaPresupuestal extends Model
{
    use HasFactory;

    protected $table = 'partidas_presupuestales';

    protected $fillable = [
        'presupuesto_id',
        'categoria_opex_id',
        'monto_asignado',
        'monto_ejercido',
        'monto_comprometido',
    ];

    protected $casts = [
        'monto_asignado' => 'decimal:2',
        'monto_ejercido' => 'decimal:2',
        'monto_comprometido' => 'decimal:2',
        'monto_disponible' => 'decimal:2',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function categoriaOpex(): BelongsTo
    {
        return $this->belongsTo(CategoriaOpex::class);
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(GastoOperativo::class, 'partida_id');
    }

    public function compromisos(): HasMany
    {
        return $this->hasMany(CompromisoReserva::class, 'partida_id');
    }

    // ─── Métodos de negocio ────────────────────────────────

    /**
     * Porcentaje de ejecución de esta partida.
     */
    public function porcentajeEjecucion(): float
    {
        if ($this->monto_asignado <= 0) {
            return 0;
        }

        return round(($this->monto_ejercido / $this->monto_asignado) * 100, 2);
    }

    /**
     * Verifica si la partida tiene fondos disponibles.
     */
    public function tieneFondos(float $monto): bool
    {
        return $this->monto_disponible >= $monto;
    }
}
