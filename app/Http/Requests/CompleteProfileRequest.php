<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
//        dd(123);
        return [
            'phone' => ['required','string','max:30'],
            'city'  => ['nullable','string','max:100'],
            'emergency_contact_name'  => ['required','string','max:100'],
            'emergency_contact_phone' => ['required','string','max:30'],
            'birthdate'  => ['required','date','before:today'],
            'handedness' => ['nullable','in:left,right,both'],
            'bow_type'   => ['nullable','in:recurve,compound,barebow,traditional'],
//            'club_name'  => ['nullable','string','max:255'],
//            'agree_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'agree_terms.accepted' => '請勾選同意條款與個資告知。',
        ];
    }
}
