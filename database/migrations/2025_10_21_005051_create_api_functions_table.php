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
        Schema::create('api_functions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('API Function 名稱');
            $table->string('identifier', 100)->unique()->comment('唯一識別碼');
            $table->text('description')->nullable()->comment('功能描述');
            $table->string('stored_procedure', 200)->comment('對應的 Stored Procedure 名稱');
            $table->boolean('is_active')->default(true)->comment('是否啟用');
            $table->unsignedBigInteger('created_by')->nullable()->comment('建立者 ID');
            $table->timestamps();
            
            // 索引
            $table->index('identifier');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_functions');
    }
};
