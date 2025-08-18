@component('mail::message')
# ¡Bienvenido(a) a la Admisión UTH!

Hola **{{ $aspirante->nombre }} {{ $aspirante->ap_paterno }}**,

Hemos generado tu **contraseña temporal** para acceder al sistema:

@component('mail::panel')
**Usuario (CURP):** {{ $aspirante->curp }}
**Contraseña temporal:** {{ $password }}
@endcomponent

> Por seguridad, te recomendamos **cambiar tu contraseña** al ingresar por primera vez.

@component('mail::button', ['url' => config('app.front_url', url('/')) ])
Ir al Portal de Admisión
@endcomponent

Si no solicitaste este registro, omite este mensaje.

Gracias,<br>
**Universidad Tecnológica de Huejotzingo**
@endcomponent
