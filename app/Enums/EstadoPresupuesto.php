<?php

namespace App\Enums;

enum EstadoPresupuesto: string
{
    case BORRADOR = 'borrador';
    case EN_REVISION = 'en_revision';
    case APROBADO = 'aprobado';
    case RECHAZADO = 'rechazado';
    case EJECUTANDO = 'ejecutando';
    case CERRADO = 'cerrado';

    /**
     * Obtiene el label legible del estado.
     */
    public function label(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::EN_REVISION => 'En Revisión',
            self::APROBADO => 'Aprobado',
            self::RECHAZADO => 'Rechazado',
            self::EJECUTANDO => 'En Ejecución',
            self::CERRADO => 'Cerrado',
        };
    }

    /**
     * Obtiene el color asociado al estado (para la API/frontend).
     */
    public function color(): string
    {
        return match ($this) {
            self::BORRADOR => 'gray',
            self::EN_REVISION => 'yellow',
            self::APROBADO => 'green',
            self::RECHAZADO => 'red',
            self::EJECUTANDO => 'blue',
            self::CERRADO => 'purple',
        };
    }

    /**
     * Verifica si el presupuesto puede ser editado.
     */
    public function esEditable(): bool
    {
        return in_array($this, [self::BORRADOR, self::RECHAZADO]);
    }

    /**
     * Verifica si el presupuesto permite registrar gastos.
     */
    public function permiteGastos(): bool
    {
        return in_array($this, [self::APROBADO, self::EJECUTANDO]);
    }
}
