<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CarreraStoreRequest;
use App\Http\Requests\V1\CarreraUpdateRequest;
use App\Http\Resources\V1\CarreraResource;
use App\Models\Carrera;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CarreraController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $q = Carrera::query();
        if ($request->filled('estatus')) $q->where('estatus', $request->integer('estatus'));
        if ($s = $request->get('search')) $q->where('carrera', 'like', "%$s%");
        $rows = $q->latest('id_carreras')->paginate((int) $request->get('per_page', 15));

        return $this->ok([
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
            'data' => CarreraResource::collection($rows->items()),
        ]);
    }

    public function store(CarreraStoreRequest $request)
    {
        $row = Carrera::create($request->validated());
        return $this->ok(new CarreraResource($row), 'Creado', 201);
    }

    public function show(Carrera $carrera)
    {
        return $this->ok(new CarreraResource($carrera));
    }

    public function update(CarreraUpdateRequest $request, Carrera $carrera)
    {
        $carrera->update($request->validated());
        return $this->ok(new CarreraResource($carrera), 'Actualizado');
    }

    public function destroy(Carrera $carrera)
    {
        $carrera->delete();
        return $this->ok(null, 'Eliminado', 204);
    }
}
