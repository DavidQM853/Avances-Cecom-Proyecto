<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'es_dinamico',
        'reglas_asignacion',
    ];

    protected $casts = [
        'es_dinamico' => 'boolean',
        'reglas_asignacion' => 'array',
    ];

    // ─── Relaciones ────────────────────────────────────────

    /**
     * Permisos del rol.
     */
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso');
    }

    /**
     * Usuarios con este rol.
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_rol')
                    ->withPivot(['id', 'departamento_id', 'alcance_global', 'vigencia_inicio', 'vigencia_fin', 'activo'])
                    ->withTimestamps();
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeEstaticos($query)
    {
        return $query->where('es_dinamico', false);
    }

    public function scopeDinamicos($query)
    {
        return $query->where('es_dinamico', true);
    }

    // ─── Métodos ───────────────────────────────────────────

    /**
     * Verifica si el rol tiene un permiso específico por slug.
     */
    public function tienePermiso(string $slug): bool
    {
        return $this->permisos()->where('slug', $slug)->exists();
    }
}
