<?php

namespace App\Http\Requests;

use App\Enums\PrelaunchContributorRole;
use App\Support\Turnstile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreComingSoonInterestRequest extends FormRequest
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
            'role' => ['required', Rule::enum(PrelaunchContributorRole::class)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:1000'],
            'company_website' => ['nullable', 'max:0'],
            'form_loaded_at' => ['required', 'integer'],
            'cf-turnstile-response' => [Turnstile::isConfigured() ? 'required' : 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! Turnstile::verify(
                $this->input('cf-turnstile-response'),
                $this->ip(),
            )) {
                $validator->errors()->add(
                    'cf-turnstile-response',
                    'Please complete the security check and try again.',
                );
            }
        });
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
            'body.min' => 'Please tell us a little more (at least 10 characters).',
        ];
    }
}
