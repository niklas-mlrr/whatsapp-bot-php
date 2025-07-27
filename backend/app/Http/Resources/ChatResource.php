<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->chat,
            'name' => $this->chat,
            'last_message' => $this->last_message ? [
                'id' => $this->last_message_id,
                'content' => $this->last_message_content,
                'type' => $this->last_message_type,
                'sender' => $this->last_message_sender,
                'sending_time' => $this->last_message_sending_time?->toIso8601String(),
            ] : null,
            'unread_count' => (int) $this->unread_count,
            'participants' => $this->participants ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0.0',
                'api_version' => '1.0',
            ],
        ];
    }
}
