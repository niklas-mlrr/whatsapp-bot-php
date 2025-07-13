<?php

namespace App\DataTransferObjects;

use App\Http\Requests\WhatsAppMessageRequest;

class WhatsAppMessageData
{
    public function __construct(
        public readonly string $from,
        public readonly string $type,
        public readonly ?string $body,
        public readonly ?string $media,
        public readonly ?string $mimetype
    ) {
    }

    public static function fromRequest(WhatsAppMessageRequest $request): self
    {
        return new self(
            from: $request->validated('from'),
            type: $request->validated('type'),
            body: $request->validated('body'),
            media: $request->validated('media'),
            mimetype: $request->validated('mimetype')
        );
    }
}
