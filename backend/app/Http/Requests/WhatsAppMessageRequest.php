<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class WhatsAppMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Hier könnte man z.B. die IP-Adresse des Absenders prüfen.
        // Fürs Erste erlauben wir alle Anfragen.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => 'required|string',
            'type' => ['required', Rule::in(['text', 'image'])],
            'body' => 'nullable|string', // Bei Bildern ist 'body' die Caption
            'media' => 'required_if:type,image|string', // Base64-String
            'mimetype' => 'required_if:type,image|string|starts_with:image/',
        ];
    }
}
