<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fel_documents', function (Blueprint $table) {
            $table->string('fel_version', 100)->nullable()->change();
        });

        Schema::table('fel_import_rows', function (Blueprint $table) {
            $table->string('error_code', 40)->nullable()->after('status')->index();
            $table->string('processing_stage', 50)->nullable()->after('error_code');
        });
    }

    public function down(): void
    {
        Schema::table('fel_import_rows', function (Blueprint $table) {
            $table->dropIndex(['error_code']);
            $table->dropColumn(['error_code', 'processing_stage']);
        });

        // Do not narrow fel_version: existing namespace values may exceed 30 characters.
    }
};
