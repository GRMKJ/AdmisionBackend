<?php

namespace App\Mail;

use App\Models\Alumno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentsValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Alumno $alumno, public string $plainPassword)
    {
        $this->alumno->loadMissing(['aspirante', 'aspirante.carrera']);
    }

    public function build()
    {
        return $this
            ->subject('Documentos validados - Bienvenido a la UTH')
            ->view('emails.documents_validated');
    }
}
