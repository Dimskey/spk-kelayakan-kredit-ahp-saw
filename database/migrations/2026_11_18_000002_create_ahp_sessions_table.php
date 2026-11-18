<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ahp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('matrix_size')->default(5);
            $table->decimal('lambda_max', 12, 6)->default(5.000000);
            $table->decimal('ci', 12, 6)->default(0.000000);
            $table->decimal('ri', 8, 4)->default(1.1200);
            $table->decimal('cr', 12, 6)->default(0.000000);
            $table->boolean('is_consistent')->default(true);
            $table->json('raw_matrix_snapshot')->nullable();
            $table->json('normalized_matrix_snapshot')->nullable();
            $table->json('weights_snapshot')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahp_sessions');
    }
};
