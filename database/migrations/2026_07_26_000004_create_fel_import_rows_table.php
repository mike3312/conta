<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fel_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fel_import_batch_id');
            $table->string('source_filename')->nullable();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('authorization_uuid', 100)->nullable();
            $table->string('status', 20)->index();
            $table->text('message')->nullable();
            $table->foreignId('fel_document_id')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->index('fel_import_batch_id', 'fel_rows_batch_idx');
            $table->index('fel_document_id', 'fel_rows_document_idx');
            $table->foreign('fel_import_batch_id', 'fel_rows_batch_fk')
                ->references('id')->on('fel_import_batches')->cascadeOnDelete();
            $table->foreign('fel_document_id', 'fel_rows_document_fk')
                ->references('id')->on('fel_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fel_import_rows');
    }
};
