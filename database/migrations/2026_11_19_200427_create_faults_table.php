<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->timestamp('sla_due_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('assigned_to');
            $table->index('status');
            $table->index('severity');
            $table->index('category');
            $table->index('sla_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faults');
    }
};
