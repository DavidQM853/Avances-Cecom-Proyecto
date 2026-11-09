<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PresupuestoRepositoryInterface
{
    /**
     * Obtiene presupuestos con filtros opcionales.
     */
    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene un presupuesto por ID con relaciones.
     */
    public function obtenerPorId(int $id, array $relaciones = []): ?\App\Models\Presupuesto;

    /**
     * Obtiene presupuestos de un departamento.
     */
    public function obtenerPorDepartamento(int $departamentoId, ?int $periodoFiscalId = null): Collection;

    /**
     * Comparativa Year-over-Year de presupuestos.
     */
    public function comparativaYoY(int $anioBase, int $anioComparar, ?int $departamentoId = null): array;

    /**
     * Crear un nuevo presupuesto.
     */
    public function crear(array $datos): \App\Models\Presupuesto;

    /**
     * Actualizar un presupuesto.
     */
    public function actualizar(int $id, array $datos): \App\Models\Presupuesto;
}
