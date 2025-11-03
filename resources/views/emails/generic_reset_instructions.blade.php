<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Instrucciones de restablecimiento</title>
</head>
<body>
  <p>Hola {{ $nombre }},</p>
  <p>Hemos recibido una solicitud para restablecer tu acceso como {{ $rol }}.</p>
  <p>Si reconoces esta solicitud, sigue las instrucciones enviadas o ponte en contacto con el área de sistemas para completar el proceso.</p>
  <p>Si no fuiste tú, puedes ignorar este mensaje.</p>
  <br>
  <p>Atentamente,<br>{{ config('app.name') }}</p>
</body>
</html>
