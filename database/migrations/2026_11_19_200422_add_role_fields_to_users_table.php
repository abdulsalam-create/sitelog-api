<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('engineer')->after('password');
            $table->string('phone')->nullable()->after('role');
            $table->string('staff_id')->nullable()->unique()->after('phone');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'staff_id']);
        });
    }
};
