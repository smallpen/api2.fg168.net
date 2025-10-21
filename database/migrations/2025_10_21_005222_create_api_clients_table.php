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
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('客戶端名稱');
            $table->enum('client_type', ['api_key', 'bearer_token', 'oauth'])->comment('客戶端類型');
            $table->string('api_key', 100)->unique()->nullable()->comment('API Key');
            $table->string('secret', 255)->nullable()->comment('Secret（加密儲存）');
            $table->timestamp('token_expires_at')->nullable()->comment('Token 過期時間');
            $table->boolean('is_active')->default(true)->comment('是否啟用');
            $table->integer('rate_limit')->default(60)->comment('速率限制（每分鐘請求數）');
            $table->timestamps();
            
            // 索引
            $table->index('api_key');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
