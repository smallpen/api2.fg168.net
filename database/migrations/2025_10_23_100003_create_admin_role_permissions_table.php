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
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->foreignId('admin_role_id')->constrained('admin_roles')->onDelete('cascade');
            $table->foreignId('admin_permission_id')->constrained('admin_permissions')->onDelete('cascade');
            $table->timestamps();
            
            $table->primary(['admin_role_id', 'admin_permission_id'], 'admin_role_permission_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
    }
};
