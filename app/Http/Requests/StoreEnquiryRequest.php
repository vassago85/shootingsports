<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'type' => ['required', 'in:general,advertise,listing'],
            'about_type' => ['nullable', 'in:organisation,provider,venue'],
            'about_id' => ['nullable', 'integer'],
            // Honeypot — must stay empty.
            'company_website' => ['nullable', 'max:0'],
            // Time trap — form rendered_at must be at least 3 seconds ago.
            'form_loaded_at' => ['required', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('company_website'))) {
            throw ValidationException::withMessages([
                'body' => 'Unable to submit this enquiry.',
            ]);
        }

        $loadedAt = (int) $this->input('form_loaded_at');
        if ($loadedAt > 0 && (time() - $loadedAt) < 3) {
            throw ValidationException::withMessages([
                'body' => 'Please wait a moment and try again.',
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_website.max' => 'Unable to submit this enquiry.',
        ];
    }
}
