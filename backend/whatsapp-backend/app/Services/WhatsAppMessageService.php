<?php

namespace App\Services;

use App\DataTransferObjects\WhatsAppMessageData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppMessageService
{
    public function handle(WhatsAppMessageData $data): void
    {
        match ($data->type) {
            'text' => $this->handleTextMessage($data),
            'image' => $this->handleImageMessage($data),
            default => $this->handleUnknownMessage($data),
        };
    }

    private function handleTextMessage(WhatsAppMessageData $data): void
    {
        Log::channel('whatsapp')->info("Text von '{$data->from}' empfangen.", [
            'message' => $data->body,
        ]);
    }

    private function handleImageMessage(WhatsAppMessageData $data): void
    {
        if (empty($data->media)) {
            Log::channel('whatsapp')->error("Bildnachricht von '{$data->from}' ohne 'media'-Daten erhalten.");
            return;
        }

        $imageData = base64_decode($data->media);
        if ($imageData === false) {
            Log::channel('whatsapp')->error("Konnte Base64-Bild von '{$data->from}' nicht dekodieren.");
            return;
        }

        // Dateinamen generieren
        $extension = Str::after($data->mimetype, 'image/');
        $filename = sprintf('uploads/%s-%s.%s', time(), uniqid(), $extension);

        // Datei mit Laravel Storage speichern (z.B. im `storage/app/public`-Ordner)
        if (Storage::disk('public')->put($filename, $imageData)) {
            Log::channel('whatsapp')->info("Bild von '{$data->from}' empfangen und gespeichert.", [
                'caption' => $data->body,
                'path' => $filename,
            ]);
        } else {
            Log::channel('whatsapp')->error("Konnte Bilddatei '{$filename}' nicht speichern.");
        }
    }

    private function handleUnknownMessage(WhatsAppMessageData $data): void
    {
        Log::channel('whatsapp')->warning("Unbekannter Nachrichtentyp '{$data->type}' von '{$data->from}' empfangen.");
    }
}
