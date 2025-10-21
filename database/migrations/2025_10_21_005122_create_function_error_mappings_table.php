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
        Schema::create('function_error_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('function_id')->comment('所屬 API Function ID');
            $table->string('error_code', 50)->comment('錯誤代碼');
            $table->integer('http_status')->comment('HTTP 狀態碼');
            $table->text('error_message')->comment('錯誤訊息');
            $table->timestamps();
            
            // 外鍵約束
            $table->foreign('function_id')
                  ->references('id')
                  ->on('api_functions')
                  ->onDelete('cascade');
            
            // 索引
            $table->index('function_id');
            $table->index(['function_id', 'error_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_error_mappings');
    }
};
