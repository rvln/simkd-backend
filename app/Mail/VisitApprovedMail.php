<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class VisitApprovedMail extends Mailable implements ShouldQueue
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
            subject: 'Kunjungan Disetujui - Panti Asuhan Dr. J. Lucas',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dateStr = $this->visit->capacity->date ?? now()->format('Y-m-d');
        $rawSlot = $this->visit->capacity->slot instanceof \BackedEnum 
            ? $this->visit->capacity->slot->value 
            : $this->visit->capacity->slot;

        $slotMap = [
            'MORNING'   => 'Sesi Pagi (08:00 - 10:00)',
            'AFTERNOON' => 'Sesi Siang (11:00 - 14:00)',
            'EVENING'   => 'Sesi Sore (15:00 - 16:00)',
            'NIGHT'     => 'Sesi Malam (17:00 - 20:00)',
        ];

        $timeStr = $slotMap[$rawSlot] ?? $rawSlot;

        return new Content(
            view: 'emails.visit_approved',
            with: [
                'visitorName' => $this->visit->user->name ?? 'Pengunjung',
                'visitorType' => $this->visit->visitor_type,
                'purpose'     => $this->visit->purpose ?? 'Tidak ada rincian tujuan khusus',
                'date' => Carbon::parse($dateStr)->locale('id')->translatedFormat('l, d F Y'),
                'time' => $timeStr,
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
