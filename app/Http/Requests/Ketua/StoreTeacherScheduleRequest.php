<?php

namespace App\Http\Requests\Ketua;

use App\Models\TeacherSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-holidays') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:'.$this->maxDateTo()],
            'mode' => ['required', 'in:'.TeacherSchedule::ModeAll.','.TeacherSchedule::ModeSelected],
            'teacher_ids' => ['exclude_unless:mode,'.TeacherSchedule::ModeSelected, 'required', 'array', 'min:1'],
            'teacher_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            'date_to.before_or_equal' => 'Rentang maksimal 62 hari sekali simpan.',
            'teacher_ids.required' => 'Centang minimal satu pengajar yang hadir.',
            'teacher_ids.min' => 'Centang minimal satu pengajar yang hadir.',
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
            'mode' => 'pilihan pengajar',
            'note' => 'catatan',
        ];
    }

    private function maxDateTo(): string
    {
        $from = strtotime((string) $this->input('date_from')) ?: time();

        return date('Y-m-d', strtotime('+61 days', $from));
    }
}
