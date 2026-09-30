<?php

namespace App\Http\Requests\Portal;

use App\Enums\AttendanceStatus;
use App\Support\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAbsenceRequestRequest extends FormRequest
{
    public const MaxDays = 14;

    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Santri) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $from = strtotime((string) $this->input('date_from')) ?: time();

        return [
            'date_from' => ['required', 'date', 'after_or_equal:today'],
            'date_to' => [
                'required',
                'date',
                'after_or_equal:date_from',
                'before_or_equal:'.date('Y-m-d', strtotime('+'.(self::MaxDays - 1).' days', $from)),
            ],
            'type' => ['required', 'in:'.AttendanceStatus::Izin->value.','.AttendanceStatus::Sakit->value],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.after_or_equal' => 'Pengajuan tidak boleh untuk tanggal yang sudah lewat.',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            'date_to.before_or_equal' => 'Satu pengajuan maksimal '.self::MaxDays.' hari.',
            'reason.min' => 'Tulis alasan minimal 5 huruf.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'tanggal mulai',
            'date_to' => 'tanggal akhir',
            'type' => 'jenis',
            'reason' => 'alasan',
        ];
    }
}
