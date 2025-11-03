<?php

namespace App\Mail;

use App\Models\Aspirante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FolioGeneradoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Aspirante $aspirante,
        public string $folio
    ) {}

    public function build()
    {
        return $this->subject('Tu folio de examen UTH')
                    ->view('emails.folio_generado');
    }
}
