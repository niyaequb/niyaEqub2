    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        /**
         * Run the migrations.
         */
        public function up(): void {
            // In 2026_03_30_100000_create_equb_sub_groups_table.php
            Schema::create('equb_sub_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equb_group_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                // Constrained to 'members' table instead of 'equb_memberships'
                $table->foreignId('inviter_member_id')->nullable()->constrained('members')->nullOnDelete();
                $table->boolean('has_won')->default(false);
                $table->timestamp('win_date')->nullable();
                $table->timestamps();
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void {
            Schema::dropIfExists('equb_sub_groups');
        }
    };