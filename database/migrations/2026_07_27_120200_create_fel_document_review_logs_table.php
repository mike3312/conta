<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fel_document_review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fel_document_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_action_uuid')->nullable();
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->text('reason')->nullable();
            $table->string('action_type', 20);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'performed_at']);
            $table->index(['fel_document_id', 'performed_at']);
            $table->index('batch_action_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fel_document_review_logs');
    }
};
