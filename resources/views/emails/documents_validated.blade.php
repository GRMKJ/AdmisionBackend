<!doctype html>
<html>
  <body style="font-family: Arial, sans-serif; color:#1f2933;">
    <p>Hola {{ optional($alumno->aspirante)->nombre }} {{ optional($alumno->aspirante)->ap_paterno }}:</p>
    <p>¡Tus documentos han sido validados con éxito! A partir de ahora formas parte de la comunidad UTH.</p>
    <p>Estos son tus datos de acceso y próximos pasos:</p>
    <ul>
      <li><strong>Matrícula:</strong> {{ $alumno->matricula }}</li>
      <li><strong>Correo institucional:</strong> {{ $alumno->correo_instituto }}</li>
      <li><strong>Contraseña temporal:</strong> {{ $plainPassword }}</li>
      <li><strong>Carrera:</strong> {{ $alumno->nombre_carrera ?? optional(optional($alumno->aspirante)->carrera)->nombre }}</li>
      <li><strong>Inicio de clases:</strong> {{ optional($alumno->fecha_inicio_clase)->format('d/m/Y') ?? '15/09/2025' }}</li>
    </ul>
    <p>Te recomendamos ingresar al portal cuanto antes para actualizar tu contraseña y revisar la información importante para tu inicio de clases.</p>
    <p>Si tienes dudas, contáctanos en aspirante@uth.edu.mx o al 227 275 9311.</p>
    <p>¡Bienvenido a la Universidad Tecnológica de Huejotzingo!</p>
  </body>
</html>
