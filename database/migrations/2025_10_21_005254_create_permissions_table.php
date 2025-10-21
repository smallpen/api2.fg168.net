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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 50)->comment('資源類型');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('資源 ID');
            $table->string('action', 50)->comment('操作動作');
            $table->timestamps();
            
            // 索引
            $table->index(['resource_type', 'resource_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
