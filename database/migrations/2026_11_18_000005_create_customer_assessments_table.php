<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('ahp_session_id')->constrained('ahp_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('c1_score');
            $table->unsignedTinyInteger('c2_score');
            $table->unsignedTinyInteger('c3_score');
            $table->unsignedTinyInteger('c4_score');
            $table->unsignedTinyInteger('c5_score');
            $table->json('evaluation_trace');
            $table->decimal('r1', 10, 6);
            $table->decimal('r2', 10, 6);
            $table->decimal('r3', 10, 6);
            $table->decimal('r4', 10, 6);
            $table->decimal('r5', 10, 6);
            $table->decimal('w1_r1', 10, 6);
            $table->decimal('w2_r2', 10, 6);
            $table->decimal('w3_r3', 10, 6);
            $table->decimal('w4_r4', 10, 6);
            $table->decimal('w5_r5', 10, 6);
            $table->decimal('preference_score', 10, 6)->index();
            $table->unsignedInteger('rank')->nullable()->index();
            $table->string('recommended_top')->index(); // 'NET_30', 'NET_14', 'CBD'
            $table->timestamps();

            $table->unique(
                ['customer_id', 'ahp_session_id'],
                'cust_assess_cust_session_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_assessments');
    }
};
