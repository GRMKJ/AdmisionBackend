<!doctype html>
<html>
  <body style="font-family: Arial, sans-serif; color:#1f2933;">
    <p>Hola {{ optional($pago->aspirante)->nombre }} {{ optional($pago->aspirante)->ap_paterno }}:</p>
    <p>Recibimos tu pago correspondiente al <strong>{{ optional($pago->configuracion)->concepto ?? 'Examen de Diagnóstico' }}</strong>.</p>
    <table style="border-collapse: collapse; margin:16px 0;">
      <tr>
        <td style="padding:4px 12px; font-weight:bold;">Referencia:</td>
        <td style="padding:4px 12px;">{{ $pago->referencia ?? 'N/D' }}</td>
      </tr>
      <tr>
        <td style="padding:4px 12px; font-weight:bold;">Monto:</td>
        <td style="padding:4px 12px;">${{ number_format((float) ($pago->monto_pagado ?? 0), 2) }} MXN</td>
      </tr>
      <tr>
        <td style="padding:4px 12px; font-weight:bold;">Fecha de pago:</td>
        <td style="padding:4px 12px;">{{ optional($pago->fecha_pago)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</td>
      </tr>
      <tr>
        <td style="padding:4px 12px; font-weight:bold;">Método:</td>
        <td style="padding:4px 12px;">{{ $pago->metodo_pago ?? 'Stripe' }}</td>
      </tr>
    </table>
    @php
      $seguroConfigId = (int) config('admissions.seguro_payment_config_id', 4);
      $shouldShowFolio = optional($pago->configuracion)->id_configuracion !== $seguroConfigId;
    @endphp
    @if($shouldShowFolio && optional($pago->aspirante)->folio_examen)
      <p>Tu folio de examen es:</p>
      <h2 style="margin:8px 0;">{{ $pago->aspirante->folio_examen }}</h2>
    @endif
    <p>Conserva este correo como comprobante. ¡Gracias por completar tu proceso!</p>
    <p>Atentamente,<br>Admisión UTH</p>
  </body>
</html>
