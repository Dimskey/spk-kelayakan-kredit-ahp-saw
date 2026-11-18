<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique();
            $table->string('customer_name')->index();
            $table->string('type')->nullable();
            $table->string('company')->nullable();
            $table->string('business_entity')->nullable()->index();
            $table->string('business_entity_name')->nullable();
            $table->string('npwp')->nullable();
            $table->string('nib')->nullable();
            $table->string('sppkp')->nullable();
            $table->string('sppkp_date')->nullable();
            $table->text('img_sppkp')->nullable();
            $table->text('img_npwp')->nullable();
            $table->string('pic')->nullable();
            $table->string('nik')->nullable();
            $table->text('img_ktp')->nullable();
            $table->string('customer_type')->nullable()->index();
            $table->text('delivery_address')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('delivery_street')->nullable();
            $table->text('billing_street')->nullable();
            $table->string('delivery_phone')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('delivery_email')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('current_payment_term')->nullable()->index();
            $table->string('status')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('import_batch_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
