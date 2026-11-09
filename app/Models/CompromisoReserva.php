<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompromisoReserva extends Model
{
    use HasFactory;

    protected $table = 'compromisos_reserva';

    protected $fillable = [
        'partida_id',
        'creado_por',
        'referencia_documento',
        'monto_original',
        'monto_liberado',
        'estado',
    ];

    protected $casts = [
        'monto_original' => 'decimal:2',
        'monto_liberado' => 'decimal:2',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function partida(): BelongsTo
    {
        return $this->belongsTo(PartidaPresupuestal::class, 'partida_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(GastoOperativo::class, 'compromiso_id');
    }

    // ─── Scopes y Métodos ──────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    public function saldoPendiente(): float
    {
        $gastosEjercidos = (float) $this->gastos()->sum('monto');
        return round($this->monto_original - $this->monto_liberado - $gastosEjercidos, 2);
    }
}
