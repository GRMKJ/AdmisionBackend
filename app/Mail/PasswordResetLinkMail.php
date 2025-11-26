<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombre;
    public string $rol;
    public string $url;

    public function __construct(string $nombre, string $rol, string $url)
    {
        $this->nombre = $nombre;
        $this->rol = $rol;
        $this->url = $url;
    }

    public function build()
    {
        return $this->subject('Restablece tu contraseña - '.config('app.name'))
            ->view('emails.password_reset_link');
    }
}
