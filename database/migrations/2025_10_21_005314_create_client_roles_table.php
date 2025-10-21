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
        Schema::create('client_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->comment('客戶端 ID');
            $table->unsignedBigInteger('role_id')->comment('角色 ID');
            $table->timestamps();
            
            // 外鍵約束
            $table->foreign('client_id')
                  ->references('id')
                  ->on('api_clients')
                  ->onDelete('cascade');
            
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');
            
            // 複合主鍵
            $table->primary(['client_id', 'role_id']);
            
            // 索引
            $table->index('client_id');
            $table->index('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_roles');
    }
};
