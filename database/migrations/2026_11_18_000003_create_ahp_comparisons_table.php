<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ahp_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ahp_session_id')->constrained('ahp_sessions')->cascadeOnDelete();
            $table->foreignId('criterion_row_id')->constrained('criteria')->cascadeOnDelete();
            $table->foreignId('criterion_col_id')->constrained('criteria')->cascadeOnDelete();
            $table->decimal('value', 12, 6);
            $table->timestamps();

            $table->unique(
                ['ahp_session_id', 'criterion_row_id', 'criterion_col_id'],
                'ahp_comp_session_row_col_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahp_comparisons');
    }
};
