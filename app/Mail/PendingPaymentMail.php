<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingPaymentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $donation;
    public $invoiceUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Donation $donation, string $invoiceUrl)
    {
        $this->donation = $donation;
        $this->invoiceUrl = $invoiceUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Menunggu Pembayaran Donasi - Panti Asuhan Dr. J. Lucas',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.pending_payment',
            with: [
                'donorName' => $this->donation->donorName,
                'amount' => $this->donation->amount,
                'invoiceUrl' => $this->invoiceUrl,
                'date' => $this->donation->created_at->locale('id')->translatedFormat('l, d F Y H:i'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
