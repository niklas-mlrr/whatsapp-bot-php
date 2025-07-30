<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function setOnline()
    {
        return response()->json(['status' => 'online']);
    }

    public function setAway()
    {
        return response()->json(['status' => 'away']);
    }

    public function setTyping($chat)
    {
        return response()->json(['status' => 'typing', 'chat' => $chat]);
    }
}
