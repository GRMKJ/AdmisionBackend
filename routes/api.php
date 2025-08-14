<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    AuthUnifiedController,
    AspiranteController, AlumnoController, CarreraController,
    DocumentoController, ConfiguracionPagoController, PagoController
};

Route::prefix('v1')->group(function () {

    /** ========== AUTH ========== */
    // Login unificado (Alumno/Aspirante/Administrativo)
    Route::post('auth/login', [AuthUnifiedController::class, 'login']);

    // Rutas protegidas por token Sanctum
    Route::middleware('auth:sanctum')->group(function () {

        // Perfil / Logout
        Route::get('auth/me',      [AuthUnifiedController::class, 'me']);
        Route::post('auth/logout', [AuthUnifiedController::class, 'logout']);

        /** ========== CATÁLOGOS + ADMIN-ONLY ========== */
        // Solo administrativos (gestión de catálogos)
        Route::apiResource('carreras', CarreraController::class)
            ->middleware('ability:role:administrativo');

        Route::apiResource('configuracion-pagos', ConfiguracionPagoController::class)
            ->middleware('ability:role:administrativo');

        /** ========== PERSONAS (según tu flujo actual) ========== */
        // Administración de aspirantes/alumnos: reservado a administrativos
        Route::apiResource('aspirantes', AspiranteController::class)
            ->middleware('ability:role:administrativo');

        Route::apiResource('alumnos', AlumnoController::class)
            ->middleware('ability:role:administrativo');

        /** ========== DOCUMENTOS (Policies + abilities selectivas) ========== */
        // Listar/crear/editar/borrar documentos:
        // - Cualquier rol autenticado puede entrar (alumno/aspirante/admin)
        // - La Policy de Documento restringe a "dueño" o "admin"
        Route::apiResource('documentos', DocumentoController::class)
            ->only(['index','show','store','update','destroy'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo');

        // Subir / borrar SOLO archivo del documento (mismo criterio que arriba)
        Route::post('documentos/{documento}/archivo',  [DocumentoController::class, 'uploadFile'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo')
            ->name('documentos.upload');

        Route::delete('documentos/{documento}/archivo', [DocumentoController::class, 'deleteFile'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo')
            ->name('documentos.deleteFile');

        // Historial de revisiones (Policy permite dueño o admin)
        Route::get('documentos/{documento}/historial', [DocumentoController::class, 'history'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo')
            ->name('documentos.history');

        // Revisar documento: SOLO administrativos (además pasa por Policy->review)
        Route::patch('documentos/{documento}/revision', [DocumentoController::class, 'review'])
            ->middleware('ability:role:administrativo')
            ->name('documentos.review');

        /** ========== PAGOS (pueden interactuar todos; Policy/validación delimita) ========== */
        // Suele ser cómodo permitir a alumno/aspirante crear/ver sus pagos.
        // Admin ve/gestiona todos.
        Route::apiResource('pagos', PagoController::class)
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo');

        // Subida/borrado de comprobante de pago (mismo criterio)
        Route::post('pagos/{pago}/comprobante',  [PagoController::class, 'uploadComprobante'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo')
            ->name('pagos.uploadComprobante');

        Route::delete('pagos/{pago}/comprobante', [PagoController::class, 'deleteComprobante'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo')
            ->name('pagos.deleteComprobante');
    });
});
