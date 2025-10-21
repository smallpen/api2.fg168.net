<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->comment('所屬客戶端 ID');
            $table->text('token')->comment('Token 值');
            $table->enum('type', ['bearer', 'oauth'])->comment('Token 類型');
            $table->timestamp('expires_at')->nullable()->comment('過期時間');
            $table->timestamp('last_used_at')->nullable()->comment('最後使用時間');
            $table->timestamps();
            
            // 外鍵約束
            $table->foreign('client_id')
                  ->references('id')
                  ->on('api_clients')
                  ->onDelete('cascade');
            
            // 索引
            $table->index('client_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
