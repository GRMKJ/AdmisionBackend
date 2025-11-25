<?php
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    AuthUnifiedController,
    AspiranteController,
    AlumnoController,
    CarreraController,
    DocumentoController,
    ConfiguracionPagoController,
    PagoController,
    AuthAspiranteController,
    BachilleratoController,
    DashboardController,
    AdministradorController,
    AdminPagoController,
    PushTokenController,
    StripeWebhookController
};

Route::get('/login', fn() => abort(401, 'No autenticado'))->name('login');

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'message' => 'API operativa',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');

Route::post('/debug/send', function (Request $r, \Kreait\Firebase\Contract\Messaging $messaging) {
    $token = $r->input('token');
    $message = CloudMessage::withTarget('token', $token)
        ->withNotification(['title' => 'Ping', 'body' => 'Hola desde backend'])
        ->withData(['deeplink' => '/admision']);
    $messaging->send($message);
    return ['ok' => true];
});

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');


Route::prefix('v1')->group(function () {

    /** ========== AUTH ========== */
    // Login unificado (Alumno/Aspirante/Administrativo)
    Route::post('auth/login', [AuthUnifiedController::class, 'login']);
    // Recuperación de acceso (autodetección de tipo y opciones)
    Route::post('auth/forgot', [AuthUnifiedController::class, 'forgot']);
    Route::post('auth/reset', [AuthUnifiedController::class, 'reset']);

    // Rutas protegidas por token Sanctum
    Route::middleware('auth:sanctum')->group(function () {

        // Perfil / Logout
        Route::get('auth/me', [AuthUnifiedController::class, 'me']);
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
            ->only(['index', 'show', 'store', 'update', 'destroy'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo');

        // Subir / borrar SOLO archivo del documento (mismo criterio que arriba)
        Route::post('documentos/{documento}/archivo', [DocumentoController::class, 'uploadFile'])
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
        Route::post('pagos/{pago}/comprobante', [PagoController::class, 'uploadComprobante'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo')
            ->name('pagos.uploadComprobante');

        Route::delete('pagos/{pago}/comprobante', [PagoController::class, 'deleteComprobante'])
            ->middleware('ability:role:alumno,role:aspirante,role:administrativo')
            ->name('pagos.deleteComprobante');

        Route::post('/aspirantes/pago', [PagoController::class, 'storeAspirantePago'])
            ->middleware('ability:role:aspirante')
            ->name('aspirantes.storePago');

        Route::get('/aspirantes/folio', [AspiranteController::class, 'checkFolio']);

        // 🔹 Luego la dinámica, restringida a números
        Route::get('/aspirantes/{aspirante}', [AspiranteController::class, 'show'])
            ->whereNumber('aspirante');


        Route::get('aspirantes/me', [AuthAspiranteController::class, 'me']);

        Route::post('aspirantes/logout', [AuthAspiranteController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum', 'ability:role:administrativo'])
        ->get('/admin/dashboard/stats', [DashboardController::class, 'stats']);

    Route::middleware(['auth:sanctum', 'ability:role:administrativo'])->group(function () {
        Route::get('/admin/aspirantes', [AdministradorController::class, 'aspirantes']);
    });

    Route::middleware(['auth:sanctum', 'ability:role:administrativo'])->prefix('admin')->group(function () {
        Route::get('/pago/{referencia}', [AdminPagoController::class, 'show']);
        Route::post('/pago/{referencia}/validar', [AdminPagoController::class, 'validar']);
        Route::post('/pago/{referencia}/invalidar', [AdminPagoController::class, 'invalidar']); // opcional
        Route::post('/pago/{referencia}/generar-folio', [AdminPagoController::class, 'generarFolio']);
    });

    Route::middleware(['auth:sanctum'])->post('/fcm/register', function (\Illuminate\Http\Request $r) {
        $user = $r->user();
        $aspId = $user?->id_aspirantes ?? null;

        \App\Models\DeviceToken::updateOrCreate(
            ['fcm_token' => $r->string('fcm_token')],
            ['id_aspirantes' => $aspId, 'platform' => $r->string('platform')]
        );

        return response()->json(['ok' => true]);
    });

    Route::middleware(['auth:sanctum', 'ability:role:aspirante'])->group(function () {
        Route::get('/aspirantes/progress', [AspiranteController::class, 'progress']);
    });
    Route::post('/aspirante/progress', [AspiranteController::class, 'updateProgress'])
    ->middleware('auth:sanctum', 'ability:role:aspirante');


    Route::middleware(['auth:sanctum', 'ability:role:aspirante'])->group(function () {
        Route::post('/me/push-tokens', [PushTokenController::class, 'store']);
        Route::delete('/me/push-tokens', [PushTokenController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'ability:role:aspirante'])->group(function () {
        Route::post('/aspirantes/academico', [AspiranteController::class, 'saveAcademicInfo']);
        Route::post('/aspirantes/folio/resend', [AspiranteController::class, 'resendFolio']);
        Route::post('pagos/stripe/session', [PagoController::class, 'createStripeSession'])
            ->name('pagos.stripe.session');
        Route::get('pagos/stripe/session/{session}', [PagoController::class, 'showStripeSession'])
            ->where('session', '[-_A-Za-z0-9]+')
            ->name('pagos.stripe.session.show');
    });

    Route::post('/admin/aspirantes/{id}/force-step', function ($id) {
        $asp = \App\Models\Aspirante::findOrFail($id);
        $asp->progress_step = 4;
        $asp->save();

        return response()->json([
            'success' => true,
            'message' => "Progreso forzado a step 4",
            'aspirante' => $asp,
        ]);
    })->middleware(['auth:sanctum', 'ability:role:administrativo']);

    Route::middleware('auth:sanctum')->group(function () {
    Route::post('/subir-documento', [DocumentoController::class, 'store']);
    Route::get('/mis-documentos', [DocumentoController::class, 'index']);
});

    // Auth aspirante
    Route::post('aspirantes/register', [AuthAspiranteController::class, 'register']);
    Route::post('aspirantes/login', [AuthAspiranteController::class, 'login']);
    Route::get('catalogos/carreras', [CarreraController::class, 'index']);
    Route::get('catalogos/bachilleratos', [BachilleratoController::class, 'index']);
    Route::post('catalogos/bachilleratos', [BachilleratoController::class, 'store']);
    Route::post('aspirantes/start', [AuthAspiranteController::class, 'start']);
});

