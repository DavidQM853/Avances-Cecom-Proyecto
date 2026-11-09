<?php

namespace App\Models;

use App\Enums\TipoGastoOperativo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaOpex extends Model
{
    use HasFactory;

    protected $table = 'categorias_opex';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'tipo_gasto',
        'categoria_padre_id',
        'activo',
    ];

    protected $casts = [
        'tipo_gasto' => TipoGastoOperativo::class,
        'activo' => 'boolean',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function padre(): BelongsTo
    {
        return $this->belongsTo(CategoriaOpex::class, 'categoria_padre_id');
    }

    public function subcategorias(): HasMany
    {
        return $this->hasMany(CategoriaOpex::class, 'categoria_padre_id');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaPresupuestal::class);
    }

    public function agregaciones(): HasMany
    {
        return $this->hasMany(AgregacionTemporal::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeRaiz($query)
    {
        return $query->whereNull('categoria_padre_id');
    }
}
