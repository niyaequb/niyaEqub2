<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equb_payments', function (Blueprint $table) {
            // Drop old foreign key and column if they exist
            if (Schema::hasColumn('equb_payments', 'equb_membership_id')) {
                $table->dropForeign(['equb_membership_id']);
                $table->dropColumn('equb_membership_id');
            }

            // Add the new sub group foreign key column
            if (!Schema::hasColumn('equb_payments', 'equb_sub_group_id')) {
                $table->foreignId('equb_sub_group_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('equb_sub_groups')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('equb_payments', function (Blueprint $table) {
            $table->dropForeign(['equb_sub_group_id']);
            $table->dropColumn('equb_sub_group_id');
            
            $table->foreignId('equb_membership_id')->nullable()->constrained('equb_memberships')->cascadeOnDelete();
        });
    }
};