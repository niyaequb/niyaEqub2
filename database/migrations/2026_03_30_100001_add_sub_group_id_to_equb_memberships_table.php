<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('equb_sub_group_id')
                  ->nullable()
                  ->after('agent_id')
                  ->constrained('equb_sub_groups')
                  ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['equb_sub_group_id']);
            $table->dropColumn('equb_sub_group_id');
        });
    }
};