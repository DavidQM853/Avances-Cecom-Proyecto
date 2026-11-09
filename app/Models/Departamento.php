<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'departamento_padre_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ─── Relaciones ────────────────────────────────────────

    /**
     * Departamento padre (jeraquía organizacional).
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_padre_id');
    }

    /**
     * Sub-departamentos hijos.
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(Departamento::class, 'departamento_padre_id');
    }

    /**
     * Usuarios asignados a este departamento.
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Presupuestos del departamento.
     */
    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }

    /**
     * Agregaciones temporales del departamento.
     */
    public function agregaciones(): HasMany
    {
        return $this->hasMany(AgregacionTemporal::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    /**
     * Solo departamentos activos.
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Solo departamentos raíz (sin padre).
     */
    public function scopeRaiz($query)
    {
        return $query->whereNull('departamento_padre_id');
    }
}
