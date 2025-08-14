<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthAlumnoController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $data = $request->validate([
            'id_aspirantes' => ['required','exists:aspirantes,id_aspirantes'],
            'matricula'     => ['required','string','max:50','unique:alumnos,matricula'],
            'password'      => ['required', Password::min(8)->numbers()->mixedCase()],
        ]);

        $alumno = Alumno::create([
            'id_aspirantes' => $data['id_aspirantes'],
            'matricula'     => $data['matricula'],
            'password'      => Hash::make($data['password']),
            'estatus'       => 1,
        ]);

        $token = $alumno->createToken('mobile', ['role:alumno'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => ['id' => $alumno->id_inscripcion, 'matricula' => $alumno->matricula, 'role' => 'alumno'],
        ], 'Registrado', 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'matricula' => ['required','string'],
            'password'  => ['required','string'],
        ]);

        $alumno = Alumno::where('matricula', $data['matricula'])->first();
        if (!$alumno || !Hash::check($data['password'], $alumno->password)) {
            return $this->error('Credenciales inválidas', 401);
        }

        $token = $alumno->createToken('mobile', ['role:alumno'])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => ['id' => $alumno->id_inscripcion, 'matricula' => $alumno->matricula, 'role' => 'alumno'],
        ], 'Login correcto');
    }

    public function me(Request $request) { return $this->ok($request->user()); }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->ok(null, 'Sesión cerrada', 204);
    }
}
