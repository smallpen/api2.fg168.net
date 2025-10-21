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
        Schema::create('function_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('function_id')->comment('所屬 API Function ID');
            $table->string('name', 100)->comment('參數名稱');
            $table->enum('data_type', ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json', 'array'])->comment('資料類型');
            $table->boolean('is_required')->default(false)->comment('是否必填');
            $table->text('default_value')->nullable()->comment('預設值');
            $table->json('validation_rules')->nullable()->comment('驗證規則（JSON 格式）');
            $table->string('sp_parameter_name', 100)->comment('對應的 SP 參數名稱');
            $table->integer('position')->default(0)->comment('參數順序');
            $table->timestamps();
            
            // 外鍵約束
            $table->foreign('function_id')
                  ->references('id')
                  ->on('api_functions')
                  ->onDelete('cascade');
            
            // 索引
            $table->index('function_id');
            $table->index(['function_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_parameters');
    }
};
