<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'tenant_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->nullOnDelete();

                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'tenant_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};