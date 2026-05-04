<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResumenDiarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $datos) {}

    public function envelope(): Envelope
    {
        $fecha = $this->datos['fecha']->locale('es')->isoFormat('D MMM YYYY');

        return new Envelope(
            subject: "Resumen operacion {$fecha} · UltimaMilla Express",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resumen-diario',
            with: ['d' => $this->datos],
        );
    }
}
