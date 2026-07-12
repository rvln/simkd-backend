<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ItemDonationRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $donation;
    public $reason;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Donation $donation, string $reason)
    {
        $this->donation = $donation;
        $this->reason = $reason;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Pemberitahuan Status Donasi Barang - Panti Asuhan Empanti')
                    ->view('emails.item_donation_rejected');
    }
}
