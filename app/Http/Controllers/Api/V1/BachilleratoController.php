<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bachillerato;
use App\Http\Resources\V1\BachilleratoResource;
use Illuminate\Http\Request;
class BachilleratoController extends Controller
{
    public function index()
    {
        $bachilleratos = Bachillerato::all();
        return BachilleratoResource::collection($bachilleratos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'municipio' => 'required|string|max:255',
            'estado' => 'required|string|max:255',
        ]);

        $bachillerato = Bachillerato::create($request->all());

        return response()->json([
            'message' => 'Bachillerato agregado con éxito',
            'data' => $bachillerato
        ], 201);
    }
}
