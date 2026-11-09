<?php

namespace App\Enums;

enum PeriodoTipo: string
{
    case DIARIO = 'diario';
    case QUINCENAL = 'quincenal';
    case MENSUAL = 'mensual';
    case TRIMESTRAL = 'trimestral';
    case SEMESTRAL = 'semestral';
    case ANUAL = 'anual';

    /**
     * Obtiene el label legible del período.
     */
    public function label(): string
    {
        return match ($this) {
            self::DIARIO => 'Diario',
            self::QUINCENAL => 'Quincenal',
            self::MENSUAL => 'Mensual',
            self::TRIMESTRAL => 'Trimestral',
            self::SEMESTRAL => 'Semestral',
            self::ANUAL => 'Anual',
        };
    }

    /**
     * Obtiene el formato de agrupación SQL para cada período.
     */
    public function sqlDateFormat(): string
    {
        return match ($this) {
            self::DIARIO => '%Y-%m-%d',
            self::QUINCENAL => '%Y-%m',         // se complementa con lógica PHP
            self::MENSUAL => '%Y-%m',
            self::TRIMESTRAL => '%Y-Q',         // se complementa con QUARTER()
            self::SEMESTRAL => '%Y-S',          // se complementa con lógica PHP
            self::ANUAL => '%Y',
        };
    }
}
