<?php

namespace App\Mail;

use App\Models\Prescription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Receta que el médico envía al correo del paciente.
 *
 * Todo el contenido sale de la base de datos. En el sistema anterior el
 * navegador enviaba el texto del correo y se podía mandar cualquier cosa
 * a nombre de la farmacia.
 */
class PrescriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Prescription $prescription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu receta médica No. {$this->prescription->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.prescription',
            with: [
                'prescription' => $this->prescription->loadMissing(['doctor.doctorProfile', 'items.product.presentation']),
            ],
        );
    }
}
