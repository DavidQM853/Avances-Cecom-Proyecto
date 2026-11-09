<?php

namespace App\Repositories\Eloquent;

use App\Models\Departamento;
use App\Models\Presupuesto;
use App\Repositories\Contracts\PresupuestoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentPresupuestoRepository implements PresupuestoRepositoryInterface
{
    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Presupuesto::with([
            'departamento:id,nombre,codigo',
            'periodoFiscal',
            'partidas.categoriaOpex:id,nombre,codigo,tipo_gasto',
        ]);

        if (!empty($filtros['departamento_id'])) {
            $query->where('departamento_id', (int) $filtros['departamento_id']);
        }

        if (!empty($filtros['anio'])) {
            $query->whereHas('periodoFiscal', fn ($q) => $q->where('anio', (int) $filtros['anio']));
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function obtenerPorId(int $id, array $relaciones = []): ?Presupuesto
    {
        $relaciones = !empty($relaciones) ? $relaciones : [
            'departamento',
            'periodoFiscal',
            'creador:id,name,email',
            'aprobador:id,name,email',
            'partidas.categoriaOpex',
        ];

        return Presupuesto::with($relaciones)->find($id);
    }

    public function obtenerPorDepartamento(int $departamentoId, ?int $periodoFiscalId = null): Collection
    {
        $query = Presupuesto::delDepartamento($departamentoId)->with(['periodoFiscal', 'partidas.categoriaOpex']);

        if ($periodoFiscalId !== null) {
            $query->delPeriodo($periodoFiscalId);
        }

        return $query->get();
    }

    public function comparativaYoY(int $anioBase, int $anioComparar, ?int $departamentoId = null): array
    {
        $deptosQuery = Departamento::whereNotNull('departamento_padre_id');
        if ($departamentoId !== null) {
            $deptosQuery->where('id', $departamentoId);
        }

        $departamentos = $deptosQuery->get();
        $comparativa = [];

        foreach ($departamentos as $depto) {
            $base = Presupuesto::where('departamento_id', $depto->id)
                ->whereHas('periodoFiscal', fn ($q) => $q->where('anio', $anioBase)->whereNull('trimestre')->whereNull('mes'))
                ->first();
            $comp = Presupuesto::where('departamento_id', $depto->id)
                ->whereHas('periodoFiscal', fn ($q) => $q->where('anio', $anioComparar)->whereNull('trimestre')->whereNull('mes'))
                ->first();

            $montoBase = (float) ($base?->monto_total ?? 0);
            $montoComp = (float) ($comp?->monto_total ?? 0);
            $delta = $montoComp - $montoBase;

            $comparativa[] = [
                'departamento' => $depto->only(['id', 'nombre', 'codigo']),
                'monto_base' => $montoBase,
                'monto_comparar' => $montoComp,
                'delta_absoluto' => $delta,
                'variacion_porcentual' => $montoBase > 0 ? round(($delta / $montoBase) * 100, 2) : 0.0,
            ];
        }

        return $comparativa;
    }

    public function crear(array $datos): Presupuesto
    {
        return Presupuesto::create($datos);
    }

    public function actualizar(int $id, array $datos): Presupuesto
    {
        $presupuesto = Presupuesto::findOrFail($id);
        $presupuesto->update($datos);

        return $presupuesto->fresh();
    }
}
