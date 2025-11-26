<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Aspirante;
use App\Models\Administrativo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\AspirantePasswordResetMail;
use App\Mail\GenericResetInstructionsMail;
use App\Mail\PasswordResetLinkMail;

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

    /**
     * POST /api/v1/auth/reset
     * Restablece la contraseña dado email + token + nueva contraseña.
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'email'                 => ['required', 'email', 'max:190'],
            'token'                 => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'max:190', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'max:190'],
            'role'                  => ['required', 'in:aspirante,alumno,administrativo'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (!$record || empty($record->token) || !Hash::check($data['token'], $record->token)) {
            return $this->error('Token inválido o ya utilizado.', 422);
        }

        $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);
        if (!empty($record->created_at)) {
            $created = Carbon::parse($record->created_at);
            if ($created->addMinutes($expiresInMinutes)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
                return $this->error('El link de restablecimiento ha expirado.', 422);
            }
        }

        $user = $this->findResettableUser($data['role'], $data['email']);
        if (!$user) {
            return $this->error('No se encontró una cuenta para este correo.', 404);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return $this->ok(null, 'Contraseña actualizada correctamente.');
    }

    /**
     * POST /api/v1/auth/forgot
     * Autodetecta el tipo de usuario a partir de "identity" y, por defecto,
     * envía correo con instrucciones. Para Aspirante, genera una contraseña
     * aleatoria nueva y la envía por correo.
     * La respuesta es genérica para evitar enumeración de usuarios.
     */
    public function forgot(Request $request)
    {
        $request->validate([
            'identity' => ['required','string','max:190'],
        ]);

        $identity = trim((string) $request->input('identity'));
        $normalizedIdentity = strtoupper($identity);

        \Log::debug('Forgot password request', [
            'raw_identity'        => $identity,
            'normalized_identity' => $normalizedIdentity,
        ]);

        // Orden preferente: aspirante -> alumno -> administrativo
        $aspirante = $this->findAspirante($identity);
        if ($aspirante && !empty($aspirante->email)) {
            try {
                $this->sendResetLinkEmail(
                    email: $aspirante->email,
                    name: trim(($aspirante->nombre ?? '').' '.($aspirante->ap_paterno ?? '')),
                    role: 'aspirante'
                );
            } catch (\Throwable $e) {
                return $this->error('No se pudo enviar el correo de restablecimiento. Intenta más tarde.', 500);
            }
            return $this->ok(null, 'Si la cuenta existe, hemos enviado un link de restablecimiento.');
        }

        $alumno = $this->findAlumno($identity);
        if ($alumno && !empty($alumno->correo_instituto)) {
            try {
                $this->sendResetLinkEmail(
                    email: $alumno->correo_instituto,
                    name: $alumno->matricula ?? 'Alumno',
                    role: 'alumno'
                );
            } catch (\Throwable $e) {
                return $this->error('No se pudo enviar el correo de restablecimiento. Intenta más tarde.', 500);
            }
            return $this->ok(null, 'Si la cuenta existe, hemos enviado un link de restablecimiento.');
        }

        $admin = $this->findAdministrativo($identity);
        if ($admin) {
            \Log::debug('Administrativo encontrado', [
                'identity'        => $identity,
                'numero_empleado' => $admin->numero_empleado,
                'email'           => $admin->email,
            ]);
        } else {
            \Log::debug('Administrativo no localizado', [
                'identity' => $identity,
            ]);
        }
        if ($admin) {
            if (!empty($admin->email)) {
                try {
                    $this->sendResetLinkEmail(
                        email: $admin->email,
                        name: trim(($admin->nombre ?? '').' '.($admin->ap_paterno ?? '')) ?: ($admin->numero_empleado ?? 'Administrativo'),
                        role: 'administrativo'
                    );
                } catch (\Throwable $e) {
                    return $this->error('No se pudo enviar el correo de restablecimiento. Intenta más tarde.', 500);
                }
            } else {
                \Log::warning('Administrativo sin email registrado para reset', [
                    'numero_empleado' => $admin->numero_empleado,
                ]);
                return $this->error('No hay un correo registrado para este administrativo. Contacta a sistemas.', 422);
            }
            return $this->ok(null, 'Si la cuenta existe, hemos enviado un link de restablecimiento.');
        }

        // Respuesta genérica siempre, sin revelar existencia
        return $this->ok(null, 'Si la cuenta existe, hemos enviado instrucciones al correo.');
    }

    // ---------- Helpers ----------

    private function inferTipo(string $identity): string
    {
        // 1) CURP: 18 caracteres alfanuméricos con patrón típico -> aspirante
        $curpRegex = '/^[A-Z]{4}\d{6}[HM][A-Z]{5}\d{2}$/i';
        if (strlen($identity) === 18 && preg_match($curpRegex, strtoupper($identity))) {
            return 'aspirante';
        }

        // 2) Administrativo por formato: username que empieza con 'ADM' (case-insensitive)
        if (preg_match('/^ADM/i', $identity)) {
            return 'administrativo';
        }

        // 3) Alumno por matrícula: ejemplo '2025XXXXXX' -> matrícula de 10 dígitos empezando con '20'
        //    Detectamos matrículas de 10 dígitos que comienzan con '20' (2000-2099)
        if (preg_match('/^20\d{8}$/', $identity)) {
            return 'alumno';
        }

        // 4) Si ningún patrón coincide, intentamos una heurística basada en la existencia en la BD
        //    (fallback: intenta buscar en los tres modelos - primera coincidencia)
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
        // Intentamos varias formas para soportar identidades con/ sin prefijo 'ADM'
        // 1) Coincidencia exacta
        $admin = Administrativo::where('numero_empleado', $numeroEmpleado)->first();
        if ($admin) return $admin;

        // 2) Si viene con prefijo 'ADM', quitamos el prefijo y buscamos
        if (preg_match('/^ADM/i', $numeroEmpleado)) {
            $stripped = preg_replace('/^ADM/i', '', $numeroEmpleado);
            if ($stripped !== '') {
                $admin = Administrativo::where('numero_empleado', $stripped)->first();
                if ($admin) return $admin;
            }
        }

        // 3) Si no tiene prefijo, probamos con 'ADM' delante (por si en la BD se guarda con prefijo)
        $withPrefix = 'ADM' . $numeroEmpleado;
        return Administrativo::where('numero_empleado', $withPrefix)->first();
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

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = max(2, (int) floor(strlen($name) * 0.3));
        $masked = substr($name, 0, $visible) . str_repeat('*', max(0, strlen($name) - $visible));
        return $domain ? ($masked . '@' . $domain) : $masked;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $last4 = substr($digits, -4);
        return '***' . $last4;
    }

    private function findResettableUser(string $role, string $email)
    {
        return match ($role) {
            'aspirante'      => Aspirante::where('email', $email)->first(),
            'alumno'         => Alumno::where('correo_instituto', $email)->first(),
            'administrativo' => Administrativo::where('email', $email)->first(),
            default          => null,
        };
    }

    /**
     * Genera y envía un link de restablecimiento de contraseña al correo indicado.
     * Usa la tabla password_reset_tokens estándar de Laravel (email, token hash, created_at).
     */
    private function sendResetLinkEmail(string $email, string $name, string $role): void
    {
        try {
            $plainToken = Str::random(64);
            // Guardamos token hasheado por seguridad
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($plainToken), 'created_at' => now()]
            );

            $front = rtrim(config('app.front_url', config('app.url')), '/');
            $path  = config('app.password_reset_path', '/restablecer');
            $resetUrl = $front.$path.'?token='.urlencode($plainToken).'&email='.urlencode($email).'&role='.urlencode($role);

            Mail::to($email)->send(new PasswordResetLinkMail(
                nombre: $name,
                rol: $role,
                url: $resetUrl,
            ));

            if (method_exists(Mail::class, 'failures') && !empty(Mail::failures())) {
                throw new \RuntimeException('El servidor SMTP devolvió fallas: '.implode(', ', Mail::failures()));
            }

            \Log::info('Link de restablecimiento enviado', [
                'email' => $email,
                'role'  => $role,
            ]);
        } catch (\Throwable $e) {
            \Log::error('No se pudo generar/enviar link de restablecimiento: '.$e->getMessage(), [
                'email' => $email,
                'role'  => $role,
            ]);
            throw $e;
        }
    }
}
