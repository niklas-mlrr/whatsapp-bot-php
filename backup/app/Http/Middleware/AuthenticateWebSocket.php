<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWebSocket
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is authenticated
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Get the channel name and socket ID from the request
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        // Verify the user has access to the requested channel
        if (!$this->userCanAccessChannel(Auth::user(), $channelName)) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        // Add user information to the request for the controller
        $request->merge([
            'user' => Auth::user(),
            'socket_id' => $socketId,
        ]);

        return $next($request);
    }

    /**
     * Check if the user has access to the requested channel.
     */
    protected function userCanAccessChannel($user, string $channelName): bool
    {
        // Public channels don't require authentication
        if (str_starts_with($channelName, 'public-')) {
            return true;
        }

        // Private user channel (e.g., private-user.123)
        if (str_starts_with($channelName, 'private-user.')) {
            $userId = str_replace('private-user.', '', $channelName);
            return $user->id == $userId;
        }

        // Private chat channel (e.g., private-chat.123)
        if (str_starts_with($channelName, 'private-chat.')) {
            $chatId = str_replace('private-chat.', '', $channelName);
            return $this->userCanAccessChat($user, $chatId);
        }

        // Presence chat channel (e.g., presence-chat.123)
        if (str_starts_with($channelName, 'presence-chat.')) {
            $chatId = str_replace('presence-chat.', '', $channelName);
            return $this->userCanAccessChat($user, $chatId);
        }

        // Default deny
        return false;
    }

    /**
     * Check if the user has access to the specified chat.
     */
    protected function userCanAccessChat($user, $chatId): bool
    {
        // Check if the user is a participant in the chat
        return $user->chats()->where('chats.id', $chatId)->exists();
    }
}
