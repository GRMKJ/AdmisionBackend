<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrativo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthAdministrativoController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $data = $request->validate([
            'numero_empleado' => ['required','string','max:50','unique:administrativos,numero_empleado'],
            'nombre'          => ['required','string','max:150'],
            'ap_paterno'      => ['nullable','string','max:150'],
            'ap_materno'      => ['nullable','string','max:150'],
            'password'        => ['required', Password::min(8)->numbers()->mixedCase()],
        ]);

        $adm = Administrativo::create([
            'numero_empleado' => $data['numero_empleado'],
            'nombre'          => $data['nombre'],
            'ap_paterno'      => $data['ap_paterno'] ?? null,
            'ap_materno'      => $data['ap_materno'] ?? null,
            'password'        => Hash::make($data['password']),
            'estatus'         => 1,
        ]);

        $token = $adm->createToken('mobile', ['role:administrativo'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => ['id' => $adm->id_administrativo, 'numero_empleado' => $adm->numero_empleado, 'role' => 'administrativo'],
        ], 'Registrado', 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'numero_empleado' => ['required','string'],
            'password'        => ['required','string'],
        ]);

        $adm = Administrativo::where('numero_empleado', $data['numero_empleado'])->first();
        if (!$adm || !Hash::check($data['password'], $adm->password)) {
            return $this->error('Credenciales inválidas', 401);
        }

        $token = $adm->createToken('mobile', ['role:administrativo'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => ['id' => $adm->id_administrativo, 'numero_empleado' => $adm->numero_empleado, 'role' => 'administrativo'],
        ], 'Login correcto');
    }

    public function me(Request $request) { return $this->ok($request->user()); }
    public function logout(Request $request){ $request->user()->currentAccessToken()->delete(); return $this->ok(null,'Sesión cerrada',204); }
}
