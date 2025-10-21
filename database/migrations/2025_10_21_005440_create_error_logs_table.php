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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->comment('錯誤類型');
            $table->text('message')->comment('錯誤訊息');
            $table->text('stack_trace')->nullable()->comment('堆疊追蹤');
            $table->json('context')->nullable()->comment('錯誤上下文');
            $table->timestamp('created_at')->useCurrent()->comment('建立時間');
            
            // 索引
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
