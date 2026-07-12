<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewVisitNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $visit;

    /**
     * Create a new message instance.
     */
    public function __construct(Visit $visit)
    {
        $this->visit = $visit;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengajuan Kunjungan Baru - SIMDK',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $rawSlot = $this->visit->capacity->slot instanceof \BackedEnum 
            ? $this->visit->capacity->slot->value 
            : $this->visit->capacity->slot;

        $slotMap = [
            'MORNING'   => 'Sesi Pagi (08:00 - 10:00)',
            'AFTERNOON' => 'Sesi Siang (11:00 - 14:00)',
            'EVENING'   => 'Sesi Sore (15:00 - 16:00)',
            'NIGHT'     => 'Sesi Malam (17:00 - 20:00)',
        ];

        $rawType = $this->visit->visitor_type;
        $typeMap = [
            'INDIVIDUAL' => 'Individu',
            'GROUP'      => 'Kelompok',
        ];

        $timeStr = $slotMap[$rawSlot] ?? $rawSlot;
        $visitorTypeStr = $typeMap[$rawType] ?? $rawType;

        return new Content(
            view: 'emails.admin.new_visit',
            with: [
                'timeStr' => $timeStr,
                'visitorTypeStr' => $visitorTypeStr,
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
