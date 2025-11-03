<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericResetInstructionsMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombre;
    public string $rol; // alumno | administrativo, etc.

    public function __construct(string $nombre, string $rol)
    {
        $this->nombre = $nombre;
        $this->rol = $rol;
    }

    public function build()
    {
        return $this->subject('Instrucciones para restablecer acceso - '.config('app.name'))
            ->view('emails.generic_reset_instructions');
    }
}
