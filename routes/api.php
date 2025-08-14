<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    AspiranteController, AlumnoController, CarreraController,
    DocumentoController, ConfiguracionPagoController, PagoController
};

Route::prefix('v1')->group(function () {

    Route::apiResource('carreras', CarreraController::class);
    Route::apiResource('aspirantes', AspiranteController::class);
    Route::apiResource('alumnos', AlumnoController::class);
    Route::apiResource('documentos', DocumentoController::class);
    Route::apiResource('configuracion-pagos', ConfiguracionPagoController::class);
    Route::apiResource('pagos', PagoController::class);

});
