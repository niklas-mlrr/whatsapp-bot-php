<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class FixUserPasswords extends Command
{
    protected $signature = 'users:fix-passwords';
    protected $description = 'Fix user passwords that are not hashed with Bcrypt';

    public function handle()
    {
        // Reset admin password to 'admin123'
        $user = User::first();
        
        if ($user) {
            $user->password = Hash::make('admin123');
            $user->save();
            $this->info("Password for user '{$user->name}' has been reset to 'admin123'");
            $this->info("Please log in with these credentials and change your password immediately.");
        } else {
            // Create a default admin user if none exists
            $user = new User();
            $user->name = 'Admin';
            $user->password = Hash::make('admin123');
            $user->save();
            $this->info("Created new admin user with password 'admin123'");
        }
        
        return 0;
    }
}
