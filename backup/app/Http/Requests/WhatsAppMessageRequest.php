<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class WhatsAppMessageRequest
 *
 * @package App\Http\Requests
 */
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
            'sender' => 'required|string|max:255',
            'chat' => 'required|string|max:255',
            'type' => 'required|string|in:text,media,location',
            'content' => 'nullable|string|max:2000',
            'messageId' => 'nullable|string|max:255',
            'timestamp' => 'nullable|numeric'
        ];
    }

    public function prepareForValidation()
    {
        // Convert string timestamp to numeric if needed
        if ($this->has('timestamp') && is_string($this->timestamp)) {
            $this->merge(['timestamp' => (float)$this->timestamp]);
        }
        
        // Set default timestamp if missing
        if (!$this->has('timestamp')) {
            $this->merge(['timestamp' => now()->timestamp]);
        }
    }
}
