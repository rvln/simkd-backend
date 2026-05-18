<?php

namespace App\Http\Requests;

use App\Enums\ReportStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in Controller via role check
    }

    public function rules(): array
    {
        return [
            'status'      => [
                'required',
                'string',
                Rule::in([
                    ReportStatusEnum::PUBLISHED->value,
                    ReportStatusEnum::REJECTED->value,
                ]),
            ],
            'admin_notes'  => ['nullable', 'string', 'max:2000'],
            'admin_title'  => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status moderasi wajib diisi.',
            'status.in'       => 'Status harus PUBLISHED atau REJECTED.',
            'admin_notes.max' => 'Catatan admin maksimal 2000 karakter.',
        ];
    }
}
