<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PresenceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Set the authenticated user's status to online.
     */
    public function setOnline(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        // Broadcast the online status to relevant channels
        broadcast(new UserPresenceChanged($user, 'online'))->toOthers();

        return response()->json([
            'status' => 'online',
            'last_seen_at' => $user->last_seen_at,
        ]);
    }

    /**
     * Set the authenticated user's status to away.
     */
    public function setAway(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'status' => 'away',
            'last_seen_at' => now(),
        ]);

        // Broadcast the away status to relevant channels
        broadcast(new UserPresenceChanged($user, 'away'))->toOthers();

        return response()->json([
            'status' => 'away',
            'last_seen_at' => $user->last_seen_at,
        ]);
    }

    /**
     * Set the typing status for the authenticated user in a chat.
     */
    public function setTyping(Chat $chat, Request $request)
    {
        $request->validate([
            'is_typing' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $isTyping = $request->input('is_typing', true);

        // Verify the user is a participant in the chat
        if (!$chat->participants->contains($user->phone)) {
            return response()->json([
                'message' => 'You are not a participant in this chat',
            ], 403);
        }

        // Broadcast the typing status to other chat participants
        broadcast(new UserTyping($chat, $user, $isTyping))->toOthers();

        return response()->json([
            'typing' => $isTyping,
            'user_id' => $user->id,
            'chat_id' => $chat->id,
        ]);
    }

    /**
     * Get the online status of users in a chat.
     */
    public function getChatPresence(Chat $chat)
    {
        $user = Auth::user();

        // Verify the user is a participant in the chat
        if (!$chat->participants->contains($user->phone)) {
            return response()->json([
                'message' => 'You are not a participant in this chat',
            ], 403);
        }

        // Get the online status of all participants
        $participants = $chat->users()
            ->select(['id', 'name', 'status', 'last_seen_at', 'avatar'])
            ->where('users.id', '!=', $user->id) // Exclude the current user
            ->get()
            ->map(function ($participant) {
                return [
                    'id' => $participant->id,
                    'name' => $participant->name,
                    'status' => $participant->status,
                    'last_seen_at' => $participant->last_seen_at,
                    'avatar' => $participant->avatar_url,
                    'is_online' => $participant->status === 'online',
                ];
            });

        return response()->json([
            'participants' => $participants,
        ]);
    }
}
