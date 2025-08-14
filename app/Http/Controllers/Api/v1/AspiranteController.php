<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AspiranteStoreRequest;
use App\Http\Requests\V1\AspiranteUpdateRequest;
use App\Http\Resources\V1\AspiranteCollection;
use App\Http\Resources\V1\AspiranteResource;
use App\Models\Aspirante;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AspiranteController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $q = Aspirante::query()->with(['carrera']);

        // filtros opcionales: ?carrera=1&estatus=1&search=juan
        if ($request->has('carrera')) {
            $q->where('id_carrera', $request->integer('carrera'));
        }
        if ($request->has('estatus')) {
            $q->where('estatus', $request->integer('estatus'));
        }
        if ($search = $request->get('search')) {
            $q->where(function ($w) use ($search) {
                $w->where('nombre', 'like', "%$search%")
                  ->orWhere('ap_paterno', 'like', "%$search%")
                  ->orWhere('ap_materno', 'like', "%$search%");
            });
        }

        $aspirantes = $q->latest('id_aspirantes')->paginate(
            perPage: (int) $request->get('per_page', 15)
        );

        return $this->ok([
            'pagination' => [
                'current_page' => $aspirantes->currentPage(),
                'per_page'     => $aspirantes->perPage(),
                'total'        => $aspirantes->total(),
                'last_page'    => $aspirantes->lastPage(),
            ],
            'data' => AspiranteResource::collection($aspirantes->items()),
        ]);
    }

    public function store(AspiranteStoreRequest $request)
    {
        $asp = Aspirante::create($request->validated());
        $asp->load('carrera');

        return $this->ok(new AspiranteResource($asp), 'Creado', 201);
    }

    public function show(Aspirante $aspirante)
    {
        $aspirante->load('carrera');
        return $this->ok(new AspiranteResource($aspirante));
    }

    public function update(AspiranteUpdateRequest $request, Aspirante $aspirante)
    {
        $aspirante->update($request->validated());
        $aspirante->load('carrera');

        return $this->ok(new AspiranteResource($aspirante), 'Actualizado');
    }

    public function destroy(Aspirante $aspirante)
    {
        $aspirante->delete();
        return $this->ok(null, 'Eliminado', 204);
    }
}
