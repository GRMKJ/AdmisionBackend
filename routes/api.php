<?php

use Illuminate\Support\Facades\Route;
use App\Http\Kernel;
use App\Http\Controllers\Api\V1\{
    AuthAlumnoController, AuthAspiranteController, AuthAdministrativoController,
    AspiranteController, AlumnoController, CarreraController, DocumentoController,
    ConfiguracionPagoController, PagoController, AuthUnifiedController
};

Route::prefix('v1')->group(function () {

    // AUTH por rol
    Route::post('auth/login', [AuthUnifiedController::class, 'login']);

    // Rutas protegidas (cualquier token Sanctum válido)
    Route::middleware('auth:sanctum')->group(function () {

        // perfil/ logout por rol (puedes unificarlos si quieres)
        Route::get('auth/me',      [AuthUnifiedController::class, 'me']);
        Route::post('auth/logout', [AuthUnifiedController::class, 'logout']);

        // (Opcional) segmentar por rol con abilities:
        // ->middleware('abilities:role:administrativo') para sólo administrativos

        // Ejemplo: administrativos gestionan catálogos
        Route::apiResource('carreras', CarreraController::class)
            ->middleware('abilities:role:administrativo');

        // Aspirantes/Alumnos consumen sus propios datos;
        // administrativos pueden ver todo
        Route::apiResource('aspirantes', AspiranteController::class)
            ->middleware('abilities:role:administrativo');

        Route::apiResource('alumnos', AlumnoController::class)
            ->middleware('abilities:role:administrativo');

        Route::apiResource('documentos', DocumentoController::class);
        Route::post('documentos/{documento}/archivo',  [DocumentoController::class, 'uploadFile']);
        Route::delete('documentos/{documento}/archivo',[DocumentoController::class, 'deleteFile']);

        Route::apiResource('configuracion-pagos', ConfiguracionPagoController::class)
            ->middleware('abilities:role:administrativo');

        Route::apiResource('pagos', PagoController::class);
        Route::post('pagos/{pago}/comprobante',  [PagoController::class, 'uploadComprobante']);
        Route::delete('pagos/{pago}/comprobante',[PagoController::class, 'deleteComprobante']);

        Route::patch('documentos/{documento}/revision', [DocumentoController::class, 'review'])
        ->middleware('abilities:role:administrativo')
        ->name('documentos.review');

        Route::get('documentos/{documento}/historial', [DocumentoController::class, 'history'])
        ->name('documentos.history');
    });
});
