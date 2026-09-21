<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('source_external_id')->nullable();
            $table->timestamp('submitted_at');
            $table->string('form_type')->nullable();
            // Legacy form submissions can put arbitrary bot payloads in the name field.
            // TEXT keeps those records importable on strict MySQL while normal names remain short.
            $table->text('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('message')->nullable();
            $table->json('interests')->nullable();
            $table->unsignedInteger('submission_count')->default(1);

            $table->string('classification')->nullable();
            $table->unsignedTinyInteger('classification_confidence')->nullable();
            $table->text('classification_reason')->nullable();
            $table->timestamp('classified_at')->nullable();

            $table->string('status')->default('pending_classification');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->text('generated_reply')->nullable();
            $table->text('final_reply')->nullable();
            $table->text('generation_reason')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('replied_at')->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
