<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\AspiranteLoginRequest;
use App\Http\Requests\V1\Auth\AspiranteRegisterRequest;
use App\Http\Resources\V1\AspiranteResource;
use App\Models\Aspirante;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\AspirantePasswordMailable;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;

class AuthAspiranteController extends Controller
{
    use ApiResponse;

    public function register(AspiranteRegisterRequest $request)
    {
        $data = $request->validated();

        $asp = Aspirante::create([
            'id_carrera' => $data['id_carrera'],
            'nombre' => $data['nombre'],
            'ap_paterno' => $data['ap_paterno'],
            'ap_materno' => $data['ap_materno'] ?? null,
            'curp' => $data['curp'], // ya viene uppercase del Request
            'password' => Hash::make($data['password']),
            'estatus' => 1,
            'fecha_registro' => now(),
            'step' => 1, // inicia en paso 1 (admisión)
        ]);

        $token = $asp->createToken('mobile', ['role:aspirante'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AspiranteResource($asp),
        ], 'Registrado', 201);
    }

    public function login(AspiranteLoginRequest $request)
    {
        $data = $request->validated();

        $asp = Aspirante::where('curp', $data['curp'])->first();

        if (!$asp || !Hash::check($data['password'], $asp->password)) {
            return $this->error('Credenciales inválidas', 401);
        }

        $token = $asp->createToken('mobile', ['role:aspirante'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AspiranteResource($asp),
        ], 'Login correcto');
    }

    public function me(Request $request)
    {
        $aspirante = $request->user();
        if ($aspirante) {
            $aspirante->loadMissing(['bachillerato', 'carrera']);
        }

        return $this->ok(new AspiranteResource($aspirante));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->ok(null, 'Sesión cerrada', 204);
    }
    public function start(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ap_paterno' => ['required', 'string', 'max:150'],
            'ap_materno' => ['nullable', 'string', 'max:150'],
            'curp' => ['required', 'string', 'max:18', 'unique:aspirantes,curp'],
            'password' => ['required', Password::min(8)->numbers()->mixedCase()],
            'email' => ['required', 'email', 'max:255'],
            'sexo' => ['required', 'in:H,M,X'],
            'fecha_nacimiento' => ['required', 'date'],
            'estado_nacimiento' => ['required', 'string', 'max:3'],
            'telefono' => ['required', 'string', 'max:15'],
            'step' => ['required', 'integer', 'min:2', 'max:6'],
        ]);

        $plainPassword = $data['password'];
        $email = $data['email'] ?? null;

        $asp = Aspirante::create([
            'nombre' => $data['nombre'],
            'ap_paterno' => $data['ap_paterno'],
            'ap_materno' => $data['ap_materno'] ?? null,
            'curp' => strtoupper($data['curp']),
            'password' => Hash::make($plainPassword),
            'estatus' => 1,              // “pre-registrado”
            'fecha_registro' => now(),
            'sexo' => $data['sexo'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'estado_nacimiento' => $data['estado_nacimiento'] ?? null,
            'email' => $email,
            'telefono' => $data['telefono'] ?? null,
            'progress_step' => $data['step'] ?? 1,
        ]);


        $token = $asp->createToken('mobile', ['role:aspirante'])->plainTextToken;

        if ($email) {
            try {
                Mail::to($email)->send(new AspirantePasswordMailable($asp, $plainPassword));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->ok([
            'token' => $token,
            'user' => [
                'id' => $asp->id_aspirantes,
                'curp' => $asp->curp,
                'role' => 'aspirante',
                'redirect_to' => '/admision/bachillerato',
            ],
        ], 'Pre-registro creado', 201);
    }

public function checkFolio(Request $request): JsonResponse
{
    $asp = Aspirante::where('user_id', $request->user()->id)->first();
    return response()->json([
        'folio' => ($asp && !empty($asp->folio_examen)) ? $asp->folio_examen : false,
    ]);
}

}
