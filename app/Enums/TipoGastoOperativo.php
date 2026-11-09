<?php

namespace App\Enums;

enum TipoGastoOperativo: string
{
    case NOMINA = 'nomina';
    case SERVICIOS = 'servicios';
    case MATERIALES = 'materiales';
    case MANTENIMIENTO = 'mantenimiento';
    case TECNOLOGIA = 'tecnologia';
    case CAPACITACION = 'capacitacion';
    case VIATICOS = 'viaticos';
    case ARRENDAMIENTO = 'arrendamiento';
    case SEGUROS = 'seguros';
    case OTROS = 'otros';

    /**
     * Obtiene el label legible del tipo de gasto.
     */
    public function label(): string
    {
        return match ($this) {
            self::NOMINA => 'Nómina y Salarios',
            self::SERVICIOS => 'Servicios Profesionales',
            self::MATERIALES => 'Materiales y Suministros',
            self::MANTENIMIENTO => 'Mantenimiento y Reparación',
            self::TECNOLOGIA => 'Tecnología e Infraestructura',
            self::CAPACITACION => 'Capacitación y Desarrollo',
            self::VIATICOS => 'Viáticos y Transportación',
            self::ARRENDAMIENTO => 'Arrendamientos',
            self::SEGUROS => 'Seguros y Fianzas',
            self::OTROS => 'Otros Gastos',
        };
    }
}
