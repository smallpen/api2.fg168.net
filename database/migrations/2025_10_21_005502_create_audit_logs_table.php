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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作者 ID');
            $table->string('action', 50)->comment('操作動作');
            $table->string('resource_type', 50)->comment('資源類型');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('資源 ID');
            $table->json('old_value')->nullable()->comment('舊值');
            $table->json('new_value')->nullable()->comment('新值');
            $table->timestamp('created_at')->useCurrent()->comment('建立時間');
            
            // 索引
            $table->index('user_id');
            $table->index('action');
            $table->index(['resource_type', 'resource_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
