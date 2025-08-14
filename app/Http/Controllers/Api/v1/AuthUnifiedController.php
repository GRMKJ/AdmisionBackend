<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Aspirante;
use App\Models\Administrativo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthUnifiedController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/auth/login
     *
     * Acepta dos modalidades:
     * 1) Declarando el tipo:
     *    { "tipo": "alumno|aspirante|administrativo", "identity": "...", "password": "..." }
     *    - alumno: identity = matricula
     *    - aspirante: identity = curp
     *    - administrativo: identity = numero_empleado
     *
     * 2) Sin tipo, autodetección:
     *    { "identity": "...", "password": "..." }
     *    - Detecta CURP por regex (18 chars), o busca en los tres modelos (primera coincidencia).
     */
    public function login(Request $request)
    {
        $request->validate([
            'tipo'     => ['nullable','in:alumno,aspirante,administrativo'],
            'identity' => ['required','string','max:190'],
            'password' => ['required','string','max:190'],
        ]);

        $tipo     = $request->input('tipo');       // opcional
        $identity = trim($request->string('identity'));
        $password = $request->string('password');

        // Si el cliente NO manda "tipo", intentamos autodetectar.
        if (!$tipo) {
            $tipo = $this->inferTipo($identity);
        }

        [$user, $role, $idField] = match ($tipo) {
            'alumno'         => [$this->findAlumno($identity), 'alumno', 'id_inscripcion'],
            'aspirante'      => [$this->findAspirante($identity), 'aspirante', 'id_aspirantes'],
            'administrativo' => [$this->findAdministrativo($identity), 'administrativo', 'id_administrativo'],
        };

        if (!$user || !Hash::check($password, $user->password)) {
            return $this->error('Credenciales inválidas', 401);
        }

        // Emite token con ability acorde al rol
        $token = $user->createToken('mobile', ["role:$role"])->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => [
                'id'    => $user->{$idField},
                'role'  => $role,
                'name'  => $this->displayName($user, $role),
                'identity' => $this->displayIdentity($user, $role),
            ],
        ], 'Login correcto');
    }

    public function me(Request $request)
    {
        $u = $request->user();
        $role = $this->resolveRoleFromToken($u);
        return $this->ok([
            'id'    => $u->{ $this->rolePk($role) } ?? $u->id ?? null,
            'role'  => $role,
            'name'  => $this->displayName($u, $role),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->ok(null, 'Sesión cerrada', 204);
    }

    // ---------- Helpers ----------

    private function inferTipo(string $identity): string
    {
        // CURP: 18 caracteres alfanuméricos con patrón típico
        $curpRegex = '/^[A-Z]{4}\d{6}[HM][A-Z]{5}\d{2}$/i';
        if (strlen($identity) === 18 && preg_match($curpRegex, strtoupper($identity))) {
            return 'aspirante';
        }

        // Heurística simple: intenta buscar en los tres
        if ($this->findAlumno($identity))         return 'alumno';
        if ($this->findAdministrativo($identity)) return 'administrativo';
        if ($this->findAspirante($identity))      return 'aspirante';

        // Por defecto, fallo con mensaje claro
        abort(response()->json([
            'success' => false,
            'message' => 'No se pudo inferir el tipo de usuario. Especifica "tipo".',
            'errors'  => ['tipo' => ['Proporciona "tipo": alumno | aspirante | administrativo.']]
        ], 422));
    }

    private function findAlumno(string $matricula): ?Alumno
    {
        return Alumno::where('matricula', $matricula)->first();
    }

    private function findAspirante(string $curp): ?Aspirante
    {
        return Aspirante::where('curp', strtoupper($curp))->first();
    }

    private function findAdministrativo(string $numeroEmpleado): ?Administrativo
    {
        return Administrativo::where('numero_empleado', $numeroEmpleado)->first();
    }

    private function resolveRoleFromToken($user): string
    {
        if ($user->tokenCan('role:administrativo')) return 'administrativo';
        if ($user->tokenCan('role:aspirante'))      return 'aspirante';
        if ($user->tokenCan('role:alumno'))         return 'alumno';
        // fallback por instancia
        if ($user instanceof Administrativo) return 'administrativo';
        if ($user instanceof Aspirante)      return 'aspirante';
        if ($user instanceof Alumno)         return 'alumno';
        return 'desconocido';
    }

    private function rolePk(string $role): string
    {
        return match ($role) {
            'administrativo' => 'id_administrativo',
            'aspirante'      => 'id_aspirantes',
            'alumno'         => 'id_inscripcion',
            default          => 'id',
        };
    }

    private function displayName($user, string $role): string
    {
        return match ($role) {
            'administrativo' => trim(($user->nombre ?? '').' '.($user->ap_paterno ?? '')),
            'aspirante'      => trim(($user->nombre ?? '').' '.($user->ap_paterno ?? '')),
            'alumno'         => $user->matricula ?? 'Alumno',
            default          => 'Usuario',
        };
    }

    private function displayIdentity($user, string $role): array
    {
        return match ($role) {
            'administrativo' => ['numero_empleado' => $user->numero_empleado],
            'aspirante'      => ['curp' => $user->curp],
            'alumno'         => ['matricula' => $user->matricula],
            default          => [],
        };
    }
}
