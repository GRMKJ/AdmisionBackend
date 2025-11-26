<?php

namespace App\Mail;

use App\Models\Pago;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pago $pago)
    {
        $this->pago->loadMissing(['aspirante', 'configuracion']);
    }

    public function build()
    {
        return $this->subject('Recibo de pago - Examen de Diagnóstico')
            ->view('emails.payment_receipt');
    }
}
