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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->comment('客戶端 ID');
            $table->unsignedBigInteger('function_id')->nullable()->comment('API Function ID');
            $table->json('request_data')->nullable()->comment('請求資料');
            $table->json('response_data')->nullable()->comment('回應資料');
            $table->integer('http_status')->comment('HTTP 狀態碼');
            $table->decimal('execution_time', 10, 4)->comment('執行時間（秒）');
            $table->string('ip_address', 45)->nullable()->comment('IP 位址');
            $table->text('user_agent')->nullable()->comment('User Agent');
            $table->timestamp('created_at')->useCurrent()->comment('建立時間');
            
            // 索引
            $table->index('client_id');
            $table->index('function_id');
            $table->index('http_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
