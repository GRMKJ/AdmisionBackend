<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Aspirante;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthAspiranteController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $data = $request->validate([
            'id_carrera' => ['required','exists:carreras,id_carreras'],
            'nombre'     => ['required','string','max:150'],
            'ap_paterno' => ['required','string','max:150'],
            'ap_materno' => ['nullable','string','max:150'],
            'curp'       => ['required','string','max:18','unique:aspirantes,curp'],
            'password'   => ['required', Password::min(8)->numbers()->mixedCase()],
        ]);

        $asp = Aspirante::create([
            'id_carrera' => $data['id_carrera'],
            'nombre'     => $data['nombre'],
            'ap_paterno' => $data['ap_paterno'],
            'ap_materno' => $data['ap_materno'] ?? null,
            'curp'       => strtoupper($data['curp']),
            'password'   => Hash::make($data['password']),
            'estatus'    => 1,
            'fecha_registro' => now(),
        ]);

        $token = $asp->createToken('mobile', ['role:aspirante'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => ['id' => $asp->id_aspirantes, 'curp' => $asp->curp, 'role' => 'aspirante'],
        ], 'Registrado', 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'curp'     => ['required','string'],
            'password' => ['required','string'],
        ]);

        $asp = Aspirante::where('curp', strtoupper($data['curp']))->first();
        if (!$asp || !Hash::check($data['password'], $asp->password)) {
            return $this->error('Credenciales inválidas', 401);
        }

        $token = $asp->createToken('mobile', ['role:aspirante'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => ['id' => $asp->id_aspirantes, 'curp' => $asp->curp, 'role' => 'aspirante'],
        ], 'Login correcto');
    }

    public function me(Request $request) { return $this->ok($request->user()); }
    public function logout(Request $request){ $request->user()->currentAccessToken()->delete(); return $this->ok(null,'Sesión cerrada',204); }
}
