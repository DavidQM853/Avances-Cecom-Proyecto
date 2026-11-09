<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = [
        'nombre',
        'slug',
        'modulo',
        'descripcion',
    ];

    // ─── Relaciones ────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso');
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeDelModulo($query, string $modulo)
    {
        return $query->where('modulo', $modulo);
    }
}
