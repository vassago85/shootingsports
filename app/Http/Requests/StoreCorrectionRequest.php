<?php

namespace App\Http\Requests;

use App\Enums\SubmissionType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Support\Turnstile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreCorrectionRequest extends FormRequest
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
            'type' => ['required', Rule::in([
                SubmissionType::Event->value,
                SubmissionType::Organisation->value,
                SubmissionType::Venue->value,
            ])],
            'subject_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(['wrong_date', 'wrong_province', 'wrong_venue', 'other'])],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'submitter_name' => ['required', 'string', 'max:120'],
            'submitter_email' => ['required', 'email', 'max:255'],
            'company_website' => ['nullable', 'max:0'],
            'cf-turnstile-response' => [Turnstile::isConfigured() ? 'required' : 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->subject() === null) {
                $validator->errors()->add('subject_id', 'That listing could not be found.');

                return;
            }

            if (! Turnstile::verify($this->input('cf-turnstile-response'), $this->ip())) {
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
                'details' => 'Unable to send this correction.',
            ]);
        }
    }

    public function subject(): Event|Organisation|Venue|null
    {
        $id = (int) $this->input('subject_id');

        return match ($this->input('type')) {
            SubmissionType::Event->value => Event::query()->find($id),
            SubmissionType::Organisation->value => Organisation::query()->find($id),
            SubmissionType::Venue->value => Venue::query()->find($id),
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'details.min' => 'Tell us what is wrong in at least a sentence.',
            'company_website.max' => 'Unable to send this correction.',
        ];
    }
}
