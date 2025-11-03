<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AlumnoStoreRequest;
use App\Http\Requests\V1\AlumnoUpdateRequest;
use App\Http\Resources\V1\AlumnoResource;
use App\Models\Alumno;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AlumnoController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $q = Alumno::query()->with(['aspirante.carrera']);

        if ($request->filled('id_aspirantes')) {
            $q->where('id_aspirantes', $request->integer('id_aspirantes'));
        }
        if ($request->filled('estatus')) {
            $q->where('estatus', $request->integer('estatus'));
        }
        if ($s = $request->get('search')) {
            $q->where(function ($w) use ($s) {
                $w->where('matricula', 'like', "%$s%")
                  ->orWhere('correo_instituto', 'like', "%$s%");
            })->orWhereHas('aspirante', function ($w) use ($s) {
                $w->where('nombre', 'like', "%$s%")
                  ->orWhere('ap_paterno', 'like', "%$s%")
                  ->orWhere('ap_materno', 'like', "%$s%");
            });
        }

        $rows = $q->latest('id_inscripcion')->paginate((int) $request->get('per_page', 15));

        return $this->ok([
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
            'data' => AlumnoResource::collection($rows->items()),
        ]);
    }

    public function store(AlumnoStoreRequest $request)
    {
        $row = Alumno::create($request->validated());
        $row->load('aspirante.carrera');
        return $this->ok(new AlumnoResource($row), 'Creado', 201);
    }

    public function show(Alumno $alumno)
    {
        $alumno->load('aspirante.carrera');
        return $this->ok(new AlumnoResource($alumno));
    }

    public function update(AlumnoUpdateRequest $request, Alumno $alumno)
    {
        $alumno->update($request->validated());
        $alumno->load('aspirante.carrera');
        return $this->ok(new AlumnoResource($alumno), 'Actualizado');
    }

    public function destroy(Alumno $alumno)
    {
        $alumno->delete();
        return $this->ok(null, 'Eliminado', 204);
    }
}
