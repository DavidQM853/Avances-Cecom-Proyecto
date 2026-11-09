<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyeccionAnual extends Model
{
    use HasFactory;

    protected $table = 'proyecciones_anuales';

    public $timestamps = false;

    protected $fillable = [
        'modelo_id',
        'departamento_id',
        'anio_base',
        'anio_objetivo',
        'fecha_corte_datos',
        'monto_proyectado',
        'generado_en',
    ];

    protected $casts = [
        'anio_base' => 'integer',
        'anio_objetivo' => 'integer',
        'fecha_corte_datos' => 'date',
        'monto_proyectado' => 'decimal:2',
        'generado_en' => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloPredictivo::class, 'modelo_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }
}
