<?php

namespace App\Services;

use App\DataTransferObjects\WhatsAppMessageData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\WhatsAppMessage;
use App\Services\WebSocketService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class WhatsAppMessageService
{
    protected WebSocketService $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    public function handle(WhatsAppMessageData $data): void
    {
        try {
            // Check for duplicate message
            $cacheKey = 'message:' . md5($data->sender . $data->content . $data->sending_time);
            if (Cache::has($cacheKey)) {
                Log::channel('whatsapp')->info('Duplicate message detected, skipping', [
                    'sender' => $data->sender,
                    'content' => $data->content,
                ]);
                return;
            }

            // Store in cache for 1 hour to prevent duplicates
            Cache::put($cacheKey, true, now()->addHour());

            // Process message based on type
            $message = match ($data->type) {
                'text' => $this->handleTextMessage($data),
                'image' => $this->handleImageMessage($data),
                'video' => $this->handleVideoMessage($data),
                'audio' => $this->handleAudioMessage($data),
                'document' => $this->handleDocumentMessage($data),
                'location' => $this->handleLocationMessage($data),
                'contact' => $this->handleContactMessage($data),
                default => $this->handleUnknownMessage($data),
            };

            // Queue WebSocket notification
            if ($message) {
                $this->webSocketService->newMessage($message);
            }
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => (array) $data,
            ]);
            
            // Retry processing the message
            if (!isset($retryCount)) {
                $retryCount = 1;
                $this->retryMessageProcessing($data, $retryCount);
            }
        }
    }

    private function handleTextMessage(WhatsAppMessageData $data): WhatsAppMessage
    {
        Log::channel('whatsapp')->info("Text message received from '{$data->sender}'", [
            'message' => $data->content,
        ]);

        return WhatsAppMessage::create([
            'sender' => $data->sender,
            'chat' => $data->chat,
            'type' => 'text',
            'direction' => 'incoming',
            'status' => 'delivered',
            'content' => $this->sanitizeContent($data->content),
            'sending_time' => $data->sending_time ?? now(),
            'metadata' => [
                'original_content' => $data->content,
                'content_length' => mb_strlen($data->content),
            ],
        ]);
    }

    private function handleImageMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        if (empty($data->media) || empty($data->mimetype)) {
            Log::channel('whatsapp')->error("Image message from '{$data->sender}' missing 'media' or 'mimetype'");
            return null;
        }

        try {
            $imageData = base64_decode($data->media);
            if ($imageData === false) {
                throw new \Exception('Failed to decode base64 image data');
            }

            // Generate filename with directory structure for better organization
            $extension = Str::after($data->mimetype, 'image/');
            $directory = 'uploads/images/' . date('Y/m/d');
            $filename = sprintf('%s/%s.%s', $directory, Str::uuid(), $extension);

            // Ensure the directory exists
            Storage::disk('public')->makeDirectory($directory);

            // Save the file
            if (!Storage::disk('public')->put($filename, $imageData)) {
                throw new \Exception('Failed to save image to storage');
            }

            // Generate thumbnail for the image
            $thumbnailPath = $this->generateThumbnail(
                storage_path('app/public/' . $filename),
                $filename,
                $extension
            );

            return WhatsAppMessage::create([
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => 'image', // Force type to image
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => $data->content ?? '', // Caption if any
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => [
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'thumbnail' => $thumbnailPath,
                    'dimensions' => $this->getImageDimensions($imageData),
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing image message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function handleVideoMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        if (empty($data->media) || empty($data->mimetype)) {
            Log::channel('whatsapp')->error("Video message from '{$data->sender}' missing 'media' or 'mimetype'");
            return null;
        }

        try {
            $videoData = base64_decode($data->media);
            if ($videoData === false) {
                throw new \Exception('Failed to decode base64 video data');
            }

            // Generate filename with directory structure
            $extension = Str::after($data->mimetype, 'video/');
            $directory = 'uploads/videos/' . date('Y/m/d');
            $filename = sprintf('%s/%s.%s', $directory, Str::uuid(), $extension);

            // Ensure the directory exists
            Storage::disk('public')->makeDirectory($directory);

            // Save the file
            if (!Storage::disk('public')->put($filename, $videoData)) {
                throw new \Exception('Failed to save video to storage');
            }

            // Generate thumbnail for the video
            $thumbnailPath = $this->generateVideoThumbnail(storage_path('app/public/' . $filename), $filename);

            return WhatsAppMessage::create([
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => 'video',
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => $data->content ?? '', // Caption if any
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => [
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'thumbnail' => $thumbnailPath,
                    'duration' => $this->getVideoDuration(storage_path('app/public/' . $filename)),
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing video message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function handleAudioMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        if (empty($data->media) || empty($data->mimetype)) {
            Log::channel('whatsapp')->error("Audio message from '{$data->sender}' missing 'media' or 'mimetype'");
            return null;
        }

        try {
            $audioData = base64_decode($data->media);
            if ($audioData === false) {
                throw new \Exception('Failed to decode base64 audio data');
            }

            // Generate filename with directory structure
            $extension = Str::after($data->mimetype, 'audio/') ?: 'mp3';
            $directory = 'uploads/audio/' . date('Y/m/d');
            $filename = sprintf('%s/%s.%s', $directory, Str::uuid(), $extension);

            // Ensure the directory exists
            Storage::disk('public')->makeDirectory($directory);

            // Save the file
            if (!Storage::disk('public')->put($filename, $audioData)) {
                throw new \Exception('Failed to save audio to storage');
            }

            return WhatsAppMessage::create([
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => 'audio',
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => $data->content ?? '',
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => [
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'duration' => $this->getAudioDuration(storage_path('app/public/' . $filename)),
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing audio message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function handleDocumentMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        if (empty($data->media) || empty($data->mimetype)) {
            Log::channel('whatsapp')->error("Document message from '{$data->sender}' missing 'media' or 'mimetype'");
            return null;
        }

        try {
            $fileData = base64_decode($data->media);
            if ($fileData === false) {
                throw new \Exception('Failed to decode base64 file data');
            }

            // Get file extension from mimetype or use a default
            $extension = $this->getExtensionFromMimeType($data->mimetype) ?? 'bin';
            $directory = 'uploads/documents/' . date('Y/m/d');
            $filename = sprintf('%s/%s.%s', $directory, Str::uuid(), $extension);

            // Ensure the directory exists
            Storage::disk('public')->makeDirectory($directory);

            // Save the file
            if (!Storage::disk('public')->put($filename, $fileData)) {
                throw new \Exception('Failed to save document to storage');
            }

            return WhatsAppMessage::create([
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => 'document',
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => $data->content ?? '', // Original filename or description
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => [
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'extension' => $extension,
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing document message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function handleLocationMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        if (empty($data->content)) {
            Log::channel('whatsapp')->error("Location message from '{$data->sender}' missing location data");
            return null;
        }

        try {
            $locationData = json_decode($data->content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid location data format');
            }

            return WhatsAppMessage::create([
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => 'location',
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => $data->content,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => [
                    'latitude' => $locationData['latitude'] ?? null,
                    'longitude' => $locationData['longitude'] ?? null,
                    'name' => $locationData['name'] ?? null,
                    'address' => $locationData['address'] ?? null,
                    'url' => $locationData['url'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing location message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'content' => $data->content,
            ]);
            return null;
        }
    }

    private function handleContactMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        if (empty($data->content)) {
            Log::channel('whatsapp')->error("Contact message from '{$data->sender}' missing contact data");
            return null;
        }

        try {
            $contactData = json_decode($data->content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid contact data format');
            }

            return WhatsAppMessage::create([
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => 'contact',
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => $data->content,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => [
                    'name' => $contactData['name'] ?? null,
                    'phone' => $contactData['phone'] ?? null,
                    'email' => $contactData['email'] ?? null,
                    'organization' => $contactData['organization'] ?? null,
                    'title' => $contactData['title'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing contact message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'content' => $data->content,
            ]);
            return null;
        }
    }

    private function handleUnknownMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        Log::channel('whatsapp')->warning("Unknown message type '{$data->type}' from '{$data->sender}'", [
            'content' => $data->content,
            'mimetype' => $data->mimetype,
        ]);

        return WhatsAppMessage::create([
            'sender' => $data->sender,
            'chat' => $data->chat,
            'type' => 'unknown',
            'direction' => 'incoming',
            'status' => 'delivered',
            'content' => $data->content,
            'media' => $data->media,
            'mimetype' => $data->mimetype,
            'sending_time' => $data->sending_time ?? now(),
            'metadata' => [
                'original_type' => $data->type,
                'content_type' => gettype($data->content),
            ],
        ]);
    }

    /**
     * Generate a thumbnail for an image
     */
    private function generateThumbnail(string $imagePath, string $originalPath, string $extension): ?string
    {
        try {
            // Skip thumbnail generation for unsupported formats
            if (!in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return null;
            }

            // Create thumbnail directory if it doesn't exist
            $thumbnailDir = 'thumbnails/' . dirname($originalPath);
            Storage::disk('public')->makeDirectory($thumbnailDir);
            
            $thumbnailPath = 'thumbnails/' . $originalPath;
            $fullThumbnailPath = storage_path('app/public/' . $thumbnailPath);
            
            // Create image instance
            $image = \Intervention\Image\Facades\Image::make($imagePath);
            
            // Resize image to max 320px width while maintaining aspect ratio
            $image->resize(320, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            
            // Save the thumbnail
            $image->save($fullThumbnailPath, 80);
            
            return $thumbnailPath;
            
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error generating thumbnail', [
                'error' => $e->getMessage(),
                'path' => $imagePath,
            ]);
            return null;
        }
    }

    /**
     * Generate a thumbnail for a video
     */
    private function generateVideoThumbnail(string $videoPath, string $originalPath): ?string
    {
        try {
            // Check if FFmpeg is available
            if (!extension_loaded('ffmpeg')) {
                return null;
            }

            $thumbnailDir = 'thumbnails/' . dirname($originalPath);
            Storage::disk('public')->makeDirectory($thumbnailDir);
            
            $thumbnailPath = 'thumbnails/' . pathinfo($originalPath, PATHINFO_FILENAME) . '.jpg';
            $fullThumbnailPath = storage_path('app/public/' . $thumbnailPath);
            
            // Use FFmpeg to capture a frame at 1 second
            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries'  => config('media.ffmpeg_path', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => config('media.ffprobe_path', '/usr/bin/ffprobe'),
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);
            
            $video = $ffmpeg->open($videoPath);
            $frame = $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(1));
            $frame->save($fullThumbnailPath);
            
            return $thumbnailPath;
            
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error generating video thumbnail', [
                'error' => $e->getMessage(),
                'path' => $videoPath,
            ]);
            return null;
        }
    }

    /**
     * Get image dimensions
     */
    private function getImageDimensions(string $imageData): ?array
    {
        try {
            $image = imagecreatefromstring($imageData);
            if ($image === false) {
                return null;
            }
            
            return [
                'width' => imagesx($image),
                'height' => imagesy($image),
            ];
            
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error getting image dimensions', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get video duration in seconds
     */
    private function getVideoDuration(string $videoPath): ?int
    {
        try {
            if (!extension_loaded('ffmpeg')) {
                return null;
            }
            
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffmpeg.binaries'  => config('media.ffmpeg_path', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => config('media.ffprobe_path', '/usr/bin/ffprobe'),
            ]);
            
            return (int) $ffprobe->format($videoPath)->get('duration');
            
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error getting video duration', [
                'error' => $e->getMessage(),
                'path' => $videoPath,
            ]);
            return null;
        }
    }

    /**
     * Get audio duration in seconds
     */
    private function getAudioDuration(string $audioPath): ?int
    {
        try {
            if (!extension_loaded('ffmpeg')) {
                return null;
            }
            
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffmpeg.binaries'  => config('media.ffmpeg_path', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => config('media.ffprobe_path', '/usr/bin/ffprobe'),
            ]);
            
            return (int) $ffprobe->format($audioPath)->get('duration');
            
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error getting audio duration', [
                'error' => $e->getMessage(),
                'path' => $audioPath,
            ]);
            return null;
        }
    }

    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMimeType(string $mimeType): ?string
    {
        $mimeToExt = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
        ];
        
        return $mimeToExt[strtolower($mimeType)] ?? null;
    }

    /**
     * Sanitize message content
     */
    private function sanitizeContent(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }
        
        // Basic XSS protection
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8', false);
        
        // Remove any remaining HTML tags
        $content = strip_tags($content);
        
        // Trim whitespace
        $content = trim($content);
        
        // Convert newlines to <br> for display
        return nl2br($content);
    }

    /**
     * Retry processing a failed message
     */
    private function retryMessageProcessing(WhatsAppMessageData $data, int $retryCount, int $maxRetries = 3): void
    {
        if ($retryCount > $maxRetries) {
            Log::channel('whatsapp')->error('Max retries reached for message processing', [
                'sender' => $data->sender,
                'chat' => $data->chat,
                'type' => $data->type,
                'retry_count' => $retryCount,
            ]);
            return;
        }

        // Exponential backoff: 5s, 15s, 45s
        $delay = 5 * (3 ** ($retryCount - 1));
        
        // Queue the message for retry
        dispatch(function () use ($data, $retryCount) {
            try {
                $this->handle($data);
            } catch (\Exception $e) {
                $this->retryMessageProcessing($data, $retryCount + 1);
            }
        })->delay(now()->addSeconds($delay));
        
        Log::channel('whatsapp')->info('Message queued for retry', [
            'sender' => $data->sender,
            'chat' => $data->chat,
            'type' => $data->type,
            'retry_count' => $retryCount,
            'next_attempt_in_seconds' => $delay,
        ]);
    }
}
