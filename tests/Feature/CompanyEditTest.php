<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyEditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant('principal');
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->createCompany($this->tenant, 'Empresa Editable', '1234567');
        $this->attach($this->user, $this->company);
    }

    public function test_associated_user_can_open_and_update_company(): void
    {
        $this->asUser($this->user, $this->company)
            ->get(route('companies.edit', $this->company))
            ->assertOk()
            ->assertSee('Editar empresa')
            ->assertSee('1234567');

        $this->patch(route('companies.update', $this->company), $this->payload([
            'name' => 'Empresa Actualizada',
            'tax_id' => '7654321',
        ]))->assertRedirect(route('companies.index'));

        $this->assertDatabaseHas('companies', [
            'id' => $this->company->id,
            'name' => 'Empresa Actualizada',
            'tax_id' => '7654321',
        ]);
    }

    public function test_unassociated_user_receives_forbidden(): void
    {
        $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->asUser($outsider, $this->company)
            ->get(route('companies.edit', $this->company))
            ->assertForbidden();

        $this->patch(route('companies.update', $this->company), $this->payload())
            ->assertForbidden();
    }

    public function test_update_preserves_tenant_id(): void
    {
        $originalTenantId = $this->company->tenant_id;

        $this->asUser($this->user, $this->company)
            ->patch(route('companies.update', $this->company), $this->payload([
                'name' => 'Nombre Nuevo',
                'tenant_id' => 999999,
            ]))
            ->assertRedirect(route('companies.index'));

        $this->assertSame($originalTenantId, $this->company->fresh()->tenant_id);
    }

    public function test_tax_id_can_be_updated(): void
    {
        $this->asUser($this->user, $this->company)
            ->put(route('companies.update', $this->company), $this->payload(['tax_id' => 'CF-998877']))
            ->assertRedirect(route('companies.index'));

        $this->assertSame('CF-998877', $this->company->fresh()->tax_id);
    }

    public function test_unique_tax_id_ignores_current_company(): void
    {
        $this->asUser($this->user, $this->company)
            ->put(route('companies.update', $this->company), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.index'));
    }

    public function test_tax_id_must_remain_globally_unique_as_required_by_database_index(): void
    {
        $other = $this->createCompany($this->tenant, 'Empresa Dos', 'NIT-EXISTENTE');
        $this->attach($this->user, $other);

        $this->asUser($this->user, $this->company)
            ->put(route('companies.update', $this->company), $this->payload(['tax_id' => 'NIT-EXISTENTE']))
            ->assertSessionHasErrors('tax_id');

        $this->assertSame('1234567', $this->company->fresh()->tax_id);
    }

    public function test_soft_deleted_company_cannot_be_edited(): void
    {
        $this->company->delete();

        $this->asUser($this->user, $this->company)
            ->get('/companies/'.$this->company->id.'/edit')
            ->assertNotFound();

        $this->put('/companies/'.$this->company->id, $this->payload())
            ->assertNotFound();
    }

    public function test_user_from_another_tenant_cannot_edit_company(): void
    {
        $otherTenant = $this->createTenant('ajeno');
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCompany = $this->createCompany($otherTenant, 'Empresa del otro usuario', '9999999');
        $this->attach($otherUser, $otherCompany);

        $this->asUser($otherUser, $otherCompany)
            ->get(route('companies.edit', $this->company))
            ->assertForbidden();

        $this->put(route('companies.update', $this->company), $this->payload())
            ->assertForbidden();
    }

    public function test_inactive_company_membership_cannot_edit(): void
    {
        $this->user->companies()->updateExistingPivot($this->company->id, ['is_active' => false]);

        $this->asUser($this->user, $this->company)
            ->get(route('companies.edit', $this->company))
            ->assertForbidden();
    }

    public function test_tax_id_change_warns_about_existing_fel_documents_without_reclassifying_them(): void
    {
        $document = FelDocument::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'authorization_uuid' => 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE',
            'dte_type' => 'FACT',
            'currency' => 'GTQ',
            'source_type' => 'XML',
            'data_level' => 'FULL_DETAIL',
            'operation_type' => 'UNKNOWN',
            'classification' => 'UNCLASSIFIED',
            'status' => 'PENDING',
            'fiscal_status' => 'ACTIVE',
            'issued_at' => now(),
            'grand_total' => 100,
            'imported_by' => $this->user->id,
        ]);

        $this->asUser($this->user, $this->company)
            ->put(route('companies.update', $this->company), $this->payload(['tax_id' => 'NIT-NUEVO']))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('fel_reclassification_recommended', fn (array $recommendation) => $recommendation['company_id'] === $this->company->id)
            ->assertSessionMissing('warning');

        $response = $this->get(route('companies.index'));
        $response->assertSee('El NIT de la empresa ha cambiado.')
            ->assertSee('Ir a reclasificación FEL')
            ->assertDontSee('php artisan')
            ->assertDontSee('fel:reclassify')
            ->assertDontSee('--company');

        $document->refresh();
        $this->assertSame('UNKNOWN', $document->operation_type->value);
        $this->assertSame('UNCLASSIFIED', $document->classification->value);
    }

    private function asUser(User $user, Company $company): static
    {
        return $this->actingAs($user)->withSession(['company_id' => $company->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->company->name,
            'legal_name' => $this->company->legal_name,
            'tax_id' => $this->company->tax_id,
            'email' => 'empresa@example.test',
            'phone' => '2222-3333',
            'address' => 'Ciudad de Guatemala',
            'city' => 'Guatemala',
            'state' => 'Guatemala',
        ], $overrides);
    }

    private function createTenant(string $suffix): Tenant
    {
        return Tenant::create([
            'name' => 'Tenant '.$suffix,
            'email' => $suffix.'@tenant.test',
            'status' => 'ACTIVE',
        ]);
    }

    private function createCompany(Tenant $tenant, string $name, string $taxId): Company
    {
        return Company::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'legal_name' => $name.' S.A.',
            'tax_id' => $taxId,
            'status' => CompanyStatus::ACTIVE,
        ]);
    }

    private function attach(User $user, Company $company, bool $active = true): void
    {
        $user->companies()->attach($company, [
            'is_owner' => true,
            'is_active' => $active,
            'joined_at' => now(),
        ]);
    }
}
