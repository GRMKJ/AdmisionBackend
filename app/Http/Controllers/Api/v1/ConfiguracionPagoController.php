<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ConfiguracionPagoStoreRequest;
use App\Http\Requests\V1\ConfiguracionPagoUpdateRequest;
use App\Http\Resources\V1\ConfiguracionPagoResource;
use App\Models\ConfiguracionPago;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConfiguracionPagoController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $q = ConfiguracionPago::query();

        if ($s = $request->get('search')) {
            $q->where('concepto', 'like', "%$s%");
        }

        // ?vigentes=1 para filtrar por fecha actual dentro de vigencia
        if ($request->boolean('vigentes')) {
            $today = Carbon::today();
            $q->where(function ($w) use ($today) {
                $w->whereNull('vigencia_inicio')->orWhereDate('vigencia_inicio', '<=', $today);
            })->where(function ($w) use ($today) {
                $w->whereNull('vigencia_fin')->orWhereDate('vigencia_fin', '>=', $today);
            });
        }

        $rows = $q->latest('id_configuracion')->paginate((int) $request->get('per_page', 15));

        return $this->ok([
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
            'data' => ConfiguracionPagoResource::collection($rows->items()),
        ]);
    }

    public function store(ConfiguracionPagoStoreRequest $request)
    {
        $row = ConfiguracionPago::create($request->validated());
        return $this->ok(new ConfiguracionPagoResource($row), 'Creado', 201);
    }

    public function show(ConfiguracionPago $configuracion_pago)
    {
        return $this->ok(new ConfiguracionPagoResource($configuracion_pago));
    }

    public function update(ConfiguracionPagoUpdateRequest $request, ConfiguracionPago $configuracion_pago)
    {
        $configuracion_pago->update($request->validated());
        return $this->ok(new ConfiguracionPagoResource($configuracion_pago), 'Actualizado');
    }

    public function destroy(ConfiguracionPago $configuracion_pago)
    {
        $configuracion_pago->delete();
        return $this->ok(null, 'Eliminado', 204);
    }
}
