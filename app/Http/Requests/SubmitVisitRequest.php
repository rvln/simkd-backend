<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVisitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'capacity_id'    => 'required|uuid|exists:capacities,id',
            'visitor_name'   => 'nullable|string|max:255',
            'purpose'        => 'nullable|string',
            'visitor_type'   => 'required|string|in:Individu,Lembaga/Instansi',
            'proposal_file'  => 'required_if:visitor_type,Lembaga/Instansi|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'bringsDonation' => 'sometimes|boolean',
            'donorPhone'     => 'required_if:bringsDonation,1,true|string|max:20',
            'items'          => 'required_if:bringsDonation,1,true|array|min:1',
            'items.*.inventory_id' => 'nullable|string',
            'items.*.name'   => 'required_without:items.*.inventory_id|string|max:255',
            'items.*.qty'    => 'required|integer|min:1',
            'items.*.image'  => 'nullable|image|max:5120',
        ];
    }
}
