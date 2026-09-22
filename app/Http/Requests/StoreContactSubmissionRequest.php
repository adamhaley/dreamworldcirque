<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:255'],
            // The hidden form_type field distinguishes which page a
            // submission came from (see contact-form.blade.php's formType
            // prop), restricted to the same two values legacy imported
            // leads already use so live and imported leads stay comparable
            // in the classifier's prompt and few-shot examples.
            'form_type' => ['required', 'string', 'in:Home Form Submission,Get_A_Quote Form Submission'],
            // Honeypot: see the comment on contact-form.blade.php's hidden
            // field and StoreChatLeadRequest's identical rule.
            'website' => ['prohibited'],
        ];
    }
}
