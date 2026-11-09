<?php

namespace App\Repositories\Eloquent;

use App\Enums\PeriodoTipo;
use App\Models\GastoOperativo;
use App\Repositories\Contracts\GastoOperativoRepositoryInterface;
use App\Services\AgregacionTemporalService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentGastoOperativoRepository implements GastoOperativoRepositoryInterface
{
    public function __construct(
        protected AgregacionTemporalService $agregacionService
    ) {}

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = GastoOperativo::with([
            'partida.categoriaOpex:id,nombre,codigo,tipo_gasto',
            'partida.presupuesto.departamento:id,nombre,codigo',
            'registradoPor:id,name,email',
            'compromiso:id,partida_id,referencia_documento,estado',
        ]);

        if (!empty($filtros['desde'])) {
            $query->where('fecha_gasto', '>=', $filtros['desde']);
        }

        if (!empty($filtros['hasta'])) {
            $query->where('fecha_gasto', '<=', $filtros['hasta']);
        }

        if (!empty($filtros['departamento_id'])) {
            $query->delDepartamento((int) $filtros['departamento_id']);
        }

        if (!empty($filtros['categoria_opex_id'])) {
            $query->deCategoria((int) $filtros['categoria_opex_id']);
        }

        if (!empty($filtros['concepto'])) {
            $query->where('concepto', 'like', '%' . $filtros['concepto'] . '%');
        }

        return $query->orderByDesc('fecha_gasto')->paginate($perPage);
    }

    public function obtenerPorId(int $id): ?GastoOperativo
    {
        return GastoOperativo::with([
            'partida.categoriaOpex',
            'partida.presupuesto.departamento',
            'registradoPor:id,name,email',
            'compromiso',
        ])->find($id);
    }

    public function obtenerPorRangoFechas(string $desde, string $hasta, ?int $departamentoId = null): Collection
    {
        $query = GastoOperativo::entreFechas($desde, $hasta);

        if ($departamentoId !== null) {
            $query->delDepartamento($departamentoId);
        }

        return $query->orderBy('fecha_gasto')->get();
    }

    public function totalPorPeriodo(
        string $desde,
        string $hasta,
        string $agrupacion,
        ?int $departamentoId = null,
        ?int $categoriaOpexId = null
    ): Collection {
        $periodo = PeriodoTipo::from($agrupacion);
        $calculados = $this->agregacionService->calcular($periodo, $desde, $hasta, $departamentoId, $categoriaOpexId);

        return new Collection($calculados->all());
    }

    public function crear(array $datos): GastoOperativo
    {
        return GastoOperativo::create($datos);
    }

    public function actualizar(int $id, array $datos): GastoOperativo
    {
        $gasto = GastoOperativo::findOrFail($id);
        $gasto->update($datos);

        return $gasto->fresh();
    }

    public function eliminar(int $id): bool
    {
        $gasto = GastoOperativo::findOrFail($id);

        return (bool) $gasto->delete();
    }
}
