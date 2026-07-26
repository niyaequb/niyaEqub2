<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the main Sub Group Draws table
        Schema::create('equb_sub_group_draws', function (Blueprint $table) {
            $table->id();
            $table->string('draw_type'); // 'random' or 'manual'
            $table->integer('target_members')->nullable(); // Needed for random draws
            $table->timestamp('draw_date');
            $table->foreignId('executed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 2. Create Pivot Table for Multiple Sub Group Winners
        Schema::create('equb_sub_group_draw_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_id')->constrained('equb_sub_group_draws')->cascadeOnDelete();
            $table->foreignId('sub_group_id')->constrained('equb_sub_groups')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equb_sub_group_draw_winners');
        Schema::dropIfExists('equb_sub_group_draws');
    }
};