<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Restablecimiento de contraseña</title>
</head>
<body>
  <p>Hola {{ $nombre }},</p>
  <p>Hemos generado una nueva contraseña para tu cuenta de aspirante (CURP: {{ $curp }}).</p>
  <p><strong>Nueva contraseña:</strong> {{ $nuevaContrasena }}</p>
  <p>Te recomendamos iniciar sesión y cambiarla de inmediato desde tu perfil.</p>
  <p>Si tú no solicitaste este cambio, ponte en contacto con soporte.</p>
  <br>
  <p>Atentamente,<br>{{ config('app.name') }}</p>
</body>
</html>
