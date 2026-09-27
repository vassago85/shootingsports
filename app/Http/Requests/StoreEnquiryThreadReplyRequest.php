<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreEnquiryThreadReplyRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'company_website' => ['nullable', 'max:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('company_website'))) {
            throw ValidationException::withMessages([
                'body' => 'Unable to send this reply.',
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_website.max' => 'Unable to send this reply.',
        ];
    }
}
