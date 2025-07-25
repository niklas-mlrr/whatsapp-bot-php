<?php

namespace App\Services;

use App\DataTransferObjects\WhatsAppMessageData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\WhatsAppMessage;

class WhatsAppMessageService
{
    public function handle(WhatsAppMessageData $data): void
    {
        // Store message in DB
        WhatsAppMessage::create([
            'sender' => $data->sender,
            'chat' => $data->chat,
            'type' => $data->type,
            'content' => $data->content, // If file, store path here
            'sending_time' => $data->sending_time,
        ]);

        match ($data->type) {
            'text' => $this->handleTextMessage($data),
            'image' => $this->handleImageMessage($data),
            default => $this->handleUnknownMessage($data),
        };
    }

    private function handleTextMessage(WhatsAppMessageData $data): void
    {
        Log::channel('whatsapp')->info("Text von '{$data->sender}' empfangen.", [
            'message' => $data->content,
        ]);
    }

    private function handleImageMessage(WhatsAppMessageData $data): void
    {
        if (empty($data->media) || empty($data->mimetype)) {
            Log::channel('whatsapp')->error("Bildnachricht von '{$data->sender}' ohne 'media' oder 'mimetype' erhalten.");
            return;
        }

        $imageData = base64_decode($data->media);
        if ($imageData === false) {
            Log::channel('whatsapp')->error("Konnte Base64-Bild von '{$data->sender}' nicht dekodieren.");
            return;
        }

        // Dateinamen generieren
        $extension = Str::after($data->mimetype, 'image/');
        $filename = sprintf('uploads/%s-%s.%s', time(), uniqid(), $extension);

        // Datei mit Laravel Storage speichern (z.B. im `storage/app/public`-Ordner)
        if (Storage::disk('public')->put($filename, $imageData)) {
            // Update the message record with the image path
            WhatsAppMessage::create([
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => $data->type,
                'content' => $filename, // Store file path in content
                'sending_time' => $data->sending_time,
            ]);
            Log::channel('whatsapp')->info("Bild von '{$data->sender}' empfangen und gespeichert.", [
                'path' => $filename,
            ]);
        } else {
            Log::channel('whatsapp')->error("Konnte Bilddatei '{$filename}' nicht speichern.");
        }
    }

    private function handleUnknownMessage(WhatsAppMessageData $data): void
    {
        Log::channel('whatsapp')->warning("Unbekannter Nachrichtentyp '{$data->type}' von '{$data->sender}' empfangen.");
    }
}
