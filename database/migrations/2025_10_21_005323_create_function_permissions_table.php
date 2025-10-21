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
        Schema::create('function_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('function_id')->comment('API Function ID');
            $table->unsignedBigInteger('client_id')->comment('客戶端 ID');
            $table->boolean('allowed')->default(true)->comment('是否允許存取');
            $table->timestamps();
            
            // 外鍵約束
            $table->foreign('function_id')
                  ->references('id')
                  ->on('api_functions')
                  ->onDelete('cascade');
            
            $table->foreign('client_id')
                  ->references('id')
                  ->on('api_clients')
                  ->onDelete('cascade');
            
            // 唯一約束
            $table->unique(['function_id', 'client_id']);
            
            // 索引
            $table->index('function_id');
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_permissions');
    }
};
