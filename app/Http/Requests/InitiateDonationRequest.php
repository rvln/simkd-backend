<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\DonationTypeEnum;
use Illuminate\Validation\Rule;

class InitiateDonationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'donorName' => 'required|string|max:255',
            'donorEmail' => 'required|email|max:255',
            'donorPhone' => 'required|string|max:20',
            'amount' => 'required|numeric|min:10000',
            'payment_channel'    => 'required|string|in:MIDTRANS,MANUAL',
            'payment_proof'      => 'required_if:payment_channel,MANUAL|mimes:jpeg,png,jpg|max:2048',
            'donor_name_privacy' => 'nullable|string|in:show,hide,anon',
        ];
    }
}
