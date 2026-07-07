<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\CompanyStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Identificación
            |--------------------------------------------------------------------------
            */

            $table->uuid('uuid')->unique();

            $table->string('name', 150);

            $table->string('legal_name', 200);

            $table->string('tax_id', 20)->unique();

            /*
            |--------------------------------------------------------------------------
            | Contacto
            |--------------------------------------------------------------------------
            */

            $table->string('email')->nullable();

            $table->string('phone', 30)->nullable();

            $table->text('address')->nullable();

            $table->string('city', 100)->nullable();

            $table->string('state', 100)->nullable();

            $table->string('country', 100)->default('Guatemala');

            $table->string('postal_code', 20)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Configuración
            |--------------------------------------------------------------------------
            */

            $table->string('currency', 3)->default('GTQ');

            $table->string('timezone')->default('America/Guatemala');

            $table->string('logo')->nullable();

            $table->string('status')
                ->default(CompanyStatus::ACTIVE->value);

            /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index('name');
            $table->index('tax_id');
            $table->index('status');
            $table->index('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
