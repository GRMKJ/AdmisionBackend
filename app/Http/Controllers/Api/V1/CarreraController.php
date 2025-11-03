<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CarreraResource;
use App\Models\Carrera;

class CarreraController extends Controller
{
    public function index()
    {
        return CarreraResource::collection(
            Carrera::query()->where('estatus', 1)->orderBy('carrera')->get()
        );
    }
}
