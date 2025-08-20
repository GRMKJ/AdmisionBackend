<!doctype html>
<html>
  <body>
    <p>Hola {{ $aspirante->nombre }} {{ $aspirante->ap_paterno }}:</p>
    <p>Tu folio de examen ha sido generado:</p>
    <h2 style="margin:0;">{{ $folio }}</h2>
    <p>Guárdalo para tus trámites y consulta.</p>
    <p>Atentamente,<br> Admisión UTH</p>
  </body>
</html>
