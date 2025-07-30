<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('chat_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            
            if (!Schema::hasColumn('chat_user', 'whatsapp_id')) {
                $table->string('whatsapp_id')->nullable()->after('user_id');
            }
        });

        Schema::table('chat_user', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('chat_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('whatsapp_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
