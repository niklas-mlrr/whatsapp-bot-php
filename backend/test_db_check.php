<?php

use App\Models\User;
use App\Models\Chat;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check user creation
$user = User::where('phone', '4917646765869')->first();
if ($user) {
    echo "User found: ID {$user->id}, Phone: {$user->phone}\n";
    
    // Check chat association
    $chat = Chat::with('users')
        ->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        })
        ->first();
    
    if ($chat) {
        echo "Chat found: ID {$chat->id} with {$chat->users->count()} users\n";
    } else {
        echo "No chat found for this user\n";
    }
} else {
    echo "No user found with this phone number\n";
}
