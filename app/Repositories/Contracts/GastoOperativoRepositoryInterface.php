<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface GastoOperativoRepositoryInterface
{
    /**
     * Listar gastos con filtros de fecha, departamento y categoría.
     */
    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtener gasto por ID.
     */
    public function obtenerPorId(int $id): ?\App\Models\GastoOperativo;

    /**
     * Obtener gastos en un rango de fechas.
     */
    public function obtenerPorRangoFechas(string $desde, string $hasta, ?int $departamentoId = null): Collection;

    /**
     * Total de gastos agrupados por período.
     */
    public function totalPorPeriodo(
        string $desde,
        string $hasta,
        string $agrupacion,
        ?int $departamentoId = null,
        ?int $categoriaOpexId = null
    ): Collection;

    /**
     * Registrar un nuevo gasto.
     */
    public function crear(array $datos): \App\Models\GastoOperativo;

    /**
     * Actualizar un gasto.
     */
    public function actualizar(int $id, array $datos): \App\Models\GastoOperativo;

    /**
     * Eliminar un gasto.
     */
    public function eliminar(int $id): bool;
}
