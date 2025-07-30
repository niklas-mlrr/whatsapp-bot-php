<?php

namespace App\Services;

use App\DataTransferObjects\WhatsAppMessageData;
use App\Models\WhatsAppMessage;
use App\Models\Chat;
use App\Models\User;
use App\Services\WebSocketService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class WhatsAppMessageService
{
    protected WebSocketService $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    public function handle(WhatsAppMessageData $data): void
    {
        $maxRetries = 3;
        $retryCount = 0;
        $lastError = null;

        while ($retryCount < $maxRetries) {
            try {
                DB::transaction(function() use ($data) {
                    if (!DB::transactionLevel()) {
                        throw new \RuntimeException('Message processing must occur within a transaction');
                    }
                    $this->processMessage($data);
                });
                return;
            } catch (\Throwable $e) {
                $retryCount++;
                $lastError = $e;
                usleep(100000 * $retryCount); // Exponential backoff
                
                Log::channel('whatsapp')->warning("Attempt {$retryCount}/{$maxRetries} failed", [
                    'error' => $e->getMessage(),
                    'message_id' => $data->messageId ?? null
                ]);
            }
        }

        throw new \RuntimeException("Failed after {$maxRetries} attempts: " . $lastError->getMessage());
    }

    protected function processMessage(WhatsAppMessageData $data): void
    {
        $chat = $this->findOrCreateChat($data->chat, $data->sender);
        
        // Create message with minimal data
        $messageData = [
            'sender' => substr($data->sender, 0, 255),
            'chat' => substr($data->chat, 0, 255),
            'type' => substr($data->type, 0, 20),
            'direction' => 'incoming',
            'status' => 'delivered',
            'content' => substr($this->sanitizeContent($data->content), 0, 1000),
            'sending_time' => $data->sending_time ?? now(),
            'metadata' => json_encode([
                'original_content' => $data->content,
                'content_length' => mb_strlen($data->content),
                'whatsapp_id' => $data->messageId,
                'type' => $data->type
            ], JSON_THROW_ON_ERROR)
        ];
        
        $messageModel = WhatsAppMessage::create($messageData);
        
        // Update chat with raw query to avoid model overhead
        DB::table('chats')
            ->where('id', $chat->id)
            ->update(['last_message_id' => $messageModel->id]);
        
        // Queue WebSocket notification
        if ($messageModel) {
            $this->webSocketService->newMessage($messageModel);
        }
    }

    private function handleTextMessage(WhatsAppMessageData $data): WhatsAppMessage
    {
        Log::channel('whatsapp')->info("Text message received from '{$data->sender}'", [
            'message' => substr($data->content, 0, 1000),
        ]);

        return WhatsAppMessage::create([
            'sender' => substr($data->sender, 0, 255),
            'chat' => substr($data->chat, 0, 255),
            'type' => substr($data->type, 0, 20),
            'direction' => 'incoming',
            'status' => 'delivered',
            'content' => substr($this->sanitizeContent($data->content), 0, 1000),
            'sending_time' => $data->sending_time ?? now(),
            'metadata' => json_encode([
                'original_content' => $data->content,
                'content_length' => mb_strlen($data->content),
                'whatsapp_id' => $data->messageId,
                'type' => $data->type
            ], JSON_THROW_ON_ERROR)
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
                'sender' => substr($data->sender, 0, 255),
                'chat' => substr($data->chat, 0, 255),
                'type' => substr($data->type, 0, 20),
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => substr($data->content, 0, 1000),
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => json_encode([
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'thumbnail' => $thumbnailPath,
                    'dimensions' => $this->getImageDimensions($imageData),
                    'whatsapp_id' => $data->messageId,
                    'type' => $data->type
                ], JSON_THROW_ON_ERROR)
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
                'sender' => substr($data->sender, 0, 255),
                'chat' => substr($data->chat, 0, 255),
                'type' => substr($data->type, 0, 20),
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => substr($data->content, 0, 1000),
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => json_encode([
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'thumbnail' => $thumbnailPath,
                    'duration' => $this->getVideoDuration(storage_path('app/public/' . $filename)),
                    'whatsapp_id' => $data->messageId,
                    'type' => $data->type
                ], JSON_THROW_ON_ERROR)
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
                'sender' => substr($data->sender, 0, 255),
                'chat' => substr($data->chat, 0, 255),
                'type' => substr($data->type, 0, 20),
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => substr($data->content, 0, 1000),
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => json_encode([
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'duration' => $this->getAudioDuration(storage_path('app/public/' . $filename)),
                    'whatsapp_id' => $data->messageId,
                    'type' => $data->type
                ], JSON_THROW_ON_ERROR)
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
                'sender' => substr($data->sender, 0, 255),
                'chat' => substr($data->chat, 0, 255),
                'type' => substr($data->type, 0, 20),
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => substr($data->content, 0, 1000),
                'media' => $filename,
                'mimetype' => $data->mimetype,
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => json_encode([
                    'original_mimetype' => $data->mimetype,
                    'file_size' => Storage::disk('public')->size($filename),
                    'extension' => $extension,
                    'whatsapp_id' => $data->messageId,
                    'type' => $data->type
                ], JSON_THROW_ON_ERROR)
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
                'sender' => substr($data->sender, 0, 255),
                'chat' => substr($data->chat, 0, 255),
                'type' => substr($data->type, 0, 20),
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => substr($data->content, 0, 1000),
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => json_encode([
                    'latitude' => $locationData['latitude'] ?? null,
                    'longitude' => $locationData['longitude'] ?? null,
                    'name' => $locationData['name'] ?? null,
                    'address' => $locationData['address'] ?? null,
                    'url' => $locationData['url'] ?? null,
                    'whatsapp_id' => $data->messageId,
                    'type' => $data->type
                ], JSON_THROW_ON_ERROR)
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing location message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'content' => substr($data->content, 0, 1000),
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
                'sender' => substr($data->sender, 0, 255),
                'chat' => substr($data->chat, 0, 255),
                'type' => substr($data->type, 0, 20),
                'direction' => 'incoming',
                'status' => 'delivered',
                'content' => substr($data->content, 0, 1000),
                'sending_time' => $data->sending_time ?? now(),
                'metadata' => json_encode([
                    'name' => $contactData['name'] ?? null,
                    'phone' => $contactData['phone'] ?? null,
                    'email' => $contactData['email'] ?? null,
                    'organization' => $contactData['organization'] ?? null,
                    'title' => $contactData['title'] ?? null,
                    'whatsapp_id' => $data->messageId,
                    'type' => $data->type
                ], JSON_THROW_ON_ERROR)
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error processing contact message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'content' => substr($data->content, 0, 1000),
            ]);
            return null;
        }
    }

    private function handleUnknownMessage(WhatsAppMessageData $data): ?WhatsAppMessage
    {
        Log::channel('whatsapp')->warning("Unknown message type '{$data->type}' from '{$data->sender}'", [
            'content' => substr($data->content, 0, 1000),
            'mimetype' => $data->mimetype,
        ]);

        return WhatsAppMessage::create([
            'sender' => substr($data->sender, 0, 255),
            'chat' => substr($data->chat, 0, 255),
            'type' => substr($data->type, 0, 20),
            'direction' => 'incoming',
            'status' => 'delivered',
            'content' => substr($data->content, 0, 1000),
            'media' => $data->media,
            'mimetype' => $data->mimetype,
            'sending_time' => $data->sending_time ?? now(),
            'metadata' => json_encode([
                'original_type' => $data->type,
                'content_type' => gettype($data->content),
                'whatsapp_id' => $data->messageId,
                'type' => $data->type
            ], JSON_THROW_ON_ERROR)
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
     * Find or create a chat for the given phone number
     */
    protected function findOrCreateChat(string $chatId, string $senderId): Chat
    {
        $chat = Chat::where('metadata->whatsapp_id', $chatId)->first();

        if (!$chat) {
            $phone = str_replace('@s.whatsapp.net', '', $senderId);
            
            try {
                $user = User::firstOrCreate(
                    ['whatsapp_id' => $senderId],
                    [
                        'name' => $senderId,
                        'email' => $senderId.'-'.Str::random(8).'@whatsapp.user',
                        'phone' => $phone,
                        'password' => bcrypt(Str::random(40))
                    ]
                );
            } catch (\Exception $e) {
                // Fallback if unique constraint fails
                $user = User::where('whatsapp_id', $senderId)->firstOrFail();
            }

            $chat = Chat::create([
                'name' => $chatId,
                'is_group' => false,
                'created_by' => $user->id,
                'metadata' => [
                    'whatsapp_id' => $chatId,
                    'created_by' => $senderId
                ]
            ]);

            $chat->users()->attach($user->id);
        }

        return $chat;
    }
    
    /**
     * Find or create a user for the given phone number
     */
    private function findOrCreateUser(string $phone): User
    {
        // First try to find by phone if it exists
        $user = User::where('phone', $phone)->first();
        
        if ($user) {
            return $user;
        }
        
        // If not found by phone, create a new user
        try {
            $user = User::create([
                'name' => substr($phone, 0, 255), // Default name is the phone number
                'email' => substr($phone . '@whatsapp.local', 0, 255), // Dummy email
                'password' => bcrypt(Str::random(16)), // Random password
                'phone' => substr($phone, 0, 20),
                'status' => 'offline',
                'last_seen_at' => now(),
                'settings' => json_encode([]) // Empty settings JSON
            ]);
            
            Log::channel('whatsapp')->info('Created new user', [
                'user_id' => $user->id,
                'phone' => $phone,
            ]);
            
            return $user;
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Failed to create user', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // If user creation fails, try to find an existing user by phone again
            // in case it was created by another process
            $user = User::where('phone', $phone)->first();
            
            if ($user) {
                return $user;
            }
            
            // If we still can't find the user, rethrow the exception
            throw $e;
        }
    }
}
