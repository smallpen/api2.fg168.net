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
        Schema::create('function_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('function_id')->comment('所屬 API Function ID');
            $table->string('field_name', 100)->comment('回應欄位名稱');
            $table->string('sp_column_name', 100)->comment('對應的 SP 欄位名稱');
            $table->string('data_type', 50)->comment('資料類型');
            $table->text('transform_rule')->nullable()->comment('轉換規則');
            $table->timestamps();
            
            // 外鍵約束
            $table->foreign('function_id')
                  ->references('id')
                  ->on('api_functions')
                  ->onDelete('cascade');
            
            // 索引
            $table->index('function_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_responses');
    }
};
