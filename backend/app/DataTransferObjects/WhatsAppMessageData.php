<?php

namespace App\DataTransferObjects;

use App\Http\Requests\WhatsAppMessageRequest;

class WhatsAppMessageData
{
    public function __construct(
        public readonly string $sender,
        public readonly string $chat,
        public readonly string $type,
        public readonly ?string $content,
        public readonly ?string $sending_time,
        public readonly ?string $media = null,
        public readonly ?string $mimetype = null
    ) {
    }

    public static function fromRequest(WhatsAppMessageRequest $request): self
    {
        return new self(
            sender: $request->validated('sender'),
            chat: $request->validated('chat'),
            type: $request->validated('type'),
            content: $request->validated('content'),
            sending_time: $request->validated('sending_time'),
            media: $request->validated('media') ?? null,
            mimetype: $request->validated('mimetype') ?? null,
        );
    }
}
