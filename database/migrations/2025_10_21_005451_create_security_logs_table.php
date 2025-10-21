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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50)->comment('事件類型');
            $table->unsignedBigInteger('client_id')->nullable()->comment('客戶端 ID');
            $table->string('ip_address', 45)->nullable()->comment('IP 位址');
            $table->json('details')->nullable()->comment('事件詳情');
            $table->timestamp('created_at')->useCurrent()->comment('建立時間');
            
            // 索引
            $table->index('event_type');
            $table->index('client_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
