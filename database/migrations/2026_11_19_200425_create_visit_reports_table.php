<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engineer_id')->constrained('users')->restrictOnDelete();
            $table->date('visit_date');
            $table->string('visit_type');
            $table->boolean('grid_power_available');
            $table->decimal('generator_hours', 6, 2);
            $table->unsignedTinyInteger('fuel_level_percent');
            $table->string('battery_health');
            $table->text('work_summary');
            $table->string('status')->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'engineer_id', 'visit_date']);
            $table->index('engineer_id');
            $table->index('status');
            $table->index('visit_type');
            $table->index('visit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_reports');
    }
};
