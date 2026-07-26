<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equb_sub_group_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equb_sub_group_id')->constrained('equb_sub_groups')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('payment_date')->nullable();
            $table->string('payment_method')->default('cash');
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equb_sub_group_payments');
    }
};