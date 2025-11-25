<?php

namespace App\Mail;

use App\Models\Aspirante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExamResultAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Aspirante $aspirante)
    {
    }

    public function build()
    {
        return $this->subject('¡Felicidades! Aprobaste tu examen UTH')
            ->view('emails.exam_result_accepted');
    }
}
