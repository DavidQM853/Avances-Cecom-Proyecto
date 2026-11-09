<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'departamento_id', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────

    /**
     * Departamento al que pertenece el usuario.
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * Roles asignados al usuario (con pivot data).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'user_rol')
                    ->withPivot(['id', 'departamento_id', 'alcance_global', 'vigencia_inicio', 'vigencia_fin', 'activo'])
                    ->withTimestamps();
    }

    /**
     * Presupuestos creados por el usuario.
     */
    public function presupuestosCreados(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'creado_por');
    }

    /**
     * Presupuestos aprobados por el usuario.
     */
    public function presupuestosAprobados(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'aprobado_por');
    }

    /**
     * Gastos registrados por el usuario.
     */
    public function gastosRegistrados(): HasMany
    {
        return $this->hasMany(GastoOperativo::class, 'registrado_por');
    }

    /**
     * Compromisos y reservas creadas por el usuario.
     */
    public function compromisosCreados(): HasMany
    {
        return $this->hasMany(CompromisoReserva::class, 'creado_por');
    }

    /**
     * Eventos de bitácora generados por el usuario.
     */
    public function eventosBitacora(): HasMany
    {
        return $this->hasMany(BitacoraEvento::class, 'actor_id');
    }

    // ─── Métodos de autorización ───────────────────────────

    /**
     * Verifica si el usuario tiene un rol específico (por slug).
     */
    public function tieneRol(string $slug): bool
    {
        return $this->roles()
                    ->where('slug', $slug)
                    ->wherePivot('activo', true)
                    ->exists();
    }

    /**
     * Verifica si el usuario tiene un permiso específico (a través de sus roles).
     */
    public function tienePermiso(string $permisoSlug): bool
    {
        return $this->roles()
                    ->wherePivot('activo', true)
                    ->whereHas('permisos', function ($query) use ($permisoSlug) {
                        $query->where('slug', $permisoSlug);
                    })
                    ->exists();
    }

    /**
     * Obtiene todos los roles activos vigentes del usuario.
     */
    public function rolesVigentes()
    {
        $hoy = now()->toDateString();

        return $this->roles()
                    ->wherePivot('activo', true)
                    ->where(function ($query) use ($hoy) {
                        $query->whereNull('user_rol.vigencia_inicio')
                              ->orWhere('user_rol.vigencia_inicio', '<=', $hoy);
                    })
                    ->where(function ($query) use ($hoy) {
                        $query->whereNull('user_rol.vigencia_fin')
                              ->orWhere('user_rol.vigencia_fin', '>=', $hoy);
                    });
    }
}
