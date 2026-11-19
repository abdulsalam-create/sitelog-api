<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_code')->unique();
            $table->string('name');
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('site_type');
            $table->string('power_source');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('power_source');
            $table->index('site_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
