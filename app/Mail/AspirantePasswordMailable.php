<?php

namespace App\Mail;

use App\Models\Aspirante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;


class AspirantePasswordMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Aspirante $aspirante;
    public string $passwordPlano;

    public function __construct(Aspirante $aspirante, string $passwordPlano)
    {
        $this->aspirante = $aspirante;
        $this->passwordPlano = $passwordPlano;
    }

    public function build()
    {
        return $this
            ->subject('UT Huejotzingo - Contraseña temporal de acceso')
            ->markdown('emails.aspirantes.password', [
                'aspirante' => $this->aspirante,
                'password'  => $this->passwordPlano,
            ]);
    }
}
