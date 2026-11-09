<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoFiscal extends Model
{
    use HasFactory;

    protected $table = 'periodos_fiscales';

    protected $fillable = [
        'anio',
        'trimestre',
        'mes',
        'fecha_inicio',
        'fecha_fin',
        'cerrado',
    ];

    protected $casts = [
        'anio' => 'integer',
        'trimestre' => 'integer',
        'mes' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'cerrado' => 'boolean',
    ];

    // ─── Relaciones ────────────────────────────────────────

    /**
     * Presupuestos asociados a este período.
     */
    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeAbiertos($query)
    {
        return $query->where('cerrado', false);
    }

    public function scopeDelAnio($query, int $anio)
    {
        return $query->where('anio', $anio);
    }

    // ─── Accessors ─────────────────────────────────────────

    /**
     * Nombre descriptivo del período: "Enero 2026", "Q1 2026", "2026", etc.
     */
    public function getNombreAttribute(): string
    {
        if ($this->mes) {
            $meses = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
                4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
                10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];
            return $meses[$this->mes] . ' ' . $this->anio;
        }

        if ($this->trimestre) {
            return "Q{$this->trimestre} {$this->anio}";
        }

        return (string) $this->anio;
    }
}
