<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewDonationNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $donation;

    /**
     * Create a new message instance.
     */
    public function __construct(Donation $donation)
    {
        $this->donation = $donation;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengajuan Donasi Baru - SIMDK',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $rawType = $this->donation->type instanceof \BackedEnum 
            ? $this->donation->type->value 
            : $this->donation->type;
            
        $typeMap = [
            'DANA'   => 'Dana',
            'BARANG' => 'Barang',
        ];
        
        $rawDelivery = $this->donation->delivery_method instanceof \BackedEnum 
            ? $this->donation->delivery_method->value 
            : $this->donation->delivery_method;

        $deliveryMap = [
            'DELIVERY' => 'Kurir/Ekspedisi',
            'DROP_OFF' => 'Diserahkan Langsung',
        ];

        $typeStr = $typeMap[$rawType] ?? $rawType;
        $deliveryStr = $deliveryMap[$rawDelivery] ?? $rawDelivery;

        return new Content(
            view: 'emails.admin.new_donation',
            with: [
                'typeStr' => $typeStr,
                'deliveryStr' => $deliveryStr,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
