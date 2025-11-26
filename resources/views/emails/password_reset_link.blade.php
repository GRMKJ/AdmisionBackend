<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Restablece tu contraseña</title>
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.4; }
    .btn { display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; text-decoration:none; border-radius:4px; }
    .btn:hover { background:#1d4ed8; }
    .code { font-family: monospace; background:#f5f5f5; padding:4px 6px; border-radius:4px; }
  </style>
</head>
<body>
  <p>Hola {{ $nombre }},</p>
  <p>Has solicitado restablecer tu contraseña como <strong>{{ $rol }}</strong>.</p>
  <p>Puedes completar el proceso haciendo clic en el siguiente botón:</p>
  <p><a href="{{ $url }}" class="btn">Restablecer contraseña</a></p>
  <p>Si el botón no funciona, copia y pega esta URL en tu navegador:</p>
  <p class="code">{{ $url }}</p>
  <p>Este enlace expirará en unos minutos. Si no solicitaste este cambio, ignora este mensaje.</p>
  <br>
  <p>Atentamente,<br>{{ config('app.name') }}</p>
</body>
</html>
