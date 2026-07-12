<?php

namespace App\Mail;

use App\Models\Visit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class VisitSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $visit;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(Visit $visit, User $user)
    {
        $this->visit = $visit;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengajuan Jadwal Kunjungan - Panti Asuhan Dr. J. Lucas',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $dateStr = $this->visit->capacity->date ?? now()->format('Y-m-d');
        $timeStr = $this->visit->capacity->slot instanceof \BackedEnum 
            ? $this->visit->capacity->slot->value 
            : $this->visit->capacity->slot;

        $slotTimeRangeMap = [
            'MORNING'   => 'Sesi Pagi (08:00 – 10:00 WITA)',
            'AFTERNOON' => 'Sesi Siang (13:00 – 15:00 WITA)',
            'EVENING'   => 'Sesi Sore (15:30 – 18:00 WITA)',
            'NIGHT'     => 'Sesi Malam (19:00 – 20:00 WITA)',
        ];

        return new Content(
            view: 'emails.visit_submitted',
            with: [
                'visitorName' => $this->user->name,
                'visitorType' => $this->visit->visitor_type,
                'purpose'     => $this->visit->purpose ?? 'Tidak ada rincian tujuan khusus',
                'date'        => Carbon::parse($dateStr)->locale('id')->translatedFormat('l, d F Y'),
                'time'        => $slotTimeRangeMap[$timeStr] ?? $timeStr,
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
