<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained();
            $table->enum('direction', ['in', 'out'])->default('in');
            $table->text('message')->nullable();
            $table->integer('media_group_id')->nullable();
            $table->enum('message_type', ['text', 'photo', 'video', 'audio','animation', 'document', 'location', 'voice'])->default('text');
            
            $table->string('file_id')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_size')->nullable();

            $table->string('mime_type')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['sent', 'delivered', 'read'])->default('sent');
            $table->bigInteger('thread_id')->nullable();
            $table->integer('duration')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('messages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['thread_id']);
        });

        Schema::dropIfExists('messages');
    }
};
