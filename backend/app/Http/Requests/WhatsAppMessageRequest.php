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
            'sender' => ['required', 'string', 'max:255'],
            'chat' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'content' => ['nullable', 'string'],
            'sending_time' => ['nullable', 'date'],
            'media' => ['nullable', 'string'],
            'mimetype' => ['nullable', 'string', 'starts_with:image/'],
        ];
    }
}
