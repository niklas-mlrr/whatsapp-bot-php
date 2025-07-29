<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Disable foreign key checks to avoid issues with dropping tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Fix chats table
        if (Schema::hasTable('chats')) {
            // Drop existing foreign keys if they exist
            Schema::table('chats', function (Blueprint $table) {
                // This will drop the foreign key constraint if it exists
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableForeignKeys('chats');
                
                foreach ($indexes as $index) {
                    if (in_array('last_message_id', $index->getColumns())) {
                        $table->dropForeign([$index->getColumns()[0]]);
                    }
                }
                
                // Make sure the column exists and is properly configured
                if (!Schema::hasColumn('chats', 'last_message_id')) {
                    $table->foreignId('last_message_id')
                          ->nullable()
                          ->after('updated_at')
                          ->constrained('messages')
                          ->nullOnDelete();
                } else {
                    // Just add the constraint if it doesn't exist
                    $table->foreignId('last_message_id')
                          ->nullable()
                          ->change();
                    
                    $table->foreign('last_message_id')
                          ->references('id')
                          ->on('messages')
                          ->nullOnDelete();
                }
            });
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // We don't want to reverse this migration as it's fixing issues
    }
};
