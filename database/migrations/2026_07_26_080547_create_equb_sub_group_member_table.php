<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equb_sub_group_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equb_sub_group_id')->constrained('equb_sub_groups')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate entries of the same member in the same sub-group
            $table->unique(['equb_sub_group_id', 'member_id'], 'sub_group_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equb_sub_group_member');
    }
};