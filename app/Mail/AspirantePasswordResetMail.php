<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AspirantePasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombre;
    public string $curp;
    public string $nuevaContrasena;

    public function __construct(string $nombre, string $curp, string $nuevaContrasena)
    {
        $this->nombre = $nombre;
        $this->curp = $curp;
        $this->nuevaContrasena = $nuevaContrasena;
    }

    public function build()
    {
        return $this->subject('Restablecimiento de contraseña - '.config('app.name'))
            ->view('emails.aspirante_password_reset');
    }
}
