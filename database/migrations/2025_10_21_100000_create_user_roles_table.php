<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行 Migration
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();

            // 確保同一個使用者不會重複擁有相同角色
            $table->unique(['user_id', 'role_id']);

            // 建立索引以提升查詢效能
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    /**
     * 回滾 Migration
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
