<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActiveCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_configures_the_users_only_active_company(): void
    {
        $user = User::factory()->create();
        $company = $this->company('Empresa Uno', '1001');
        $this->attach($user, $company);

        $this->followingRedirects()->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertSame($company->id, session('company_id'));
        $this->assertSame($company->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_a_valid_active_company_is_preserved_when_user_has_several(): void
    {
        $user = User::factory()->create();
        $first = $this->company('Primera', '1002');
        $second = $this->company('Segunda', '1003');
        $this->attach($user, $first);
        $this->attach($user, $second);

        $this->actingAs($user)
            ->withSession(['company_id' => $second->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('company_id', $second->id);
    }

    public function test_a_company_from_another_user_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownCompany = $this->company('Propia', '1004');
        $otherCompany = $this->company('Ajena', '1005');
        $this->attach($user, $ownCompany);
        $this->attach($otherUser, $otherCompany);

        $this->actingAs($user)
            ->post(route('companies.switch'), ['company_id' => $otherCompany->id])
            ->assertForbidden();

        $this->assertSame($ownCompany->id, session('company_id'));
    }

    public function test_an_invalid_or_inactive_session_company_is_replaced(): void
    {
        $user = User::factory()->create();
        $active = $this->company('Activa', '1006');
        $inactive = $this->company('Inactiva', '1007', CompanyStatus::INACTIVE);
        $this->attach($user, $active);
        $this->attach($user, $inactive);

        $this->actingAs($user)
            ->withSession(['company_id' => $inactive->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('company_id', $active->id);
    }

    public function test_new_company_becomes_active_after_creation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('companies.store'), $this->companyPayload('Nueva Empresa', '1008'))
            ->assertRedirect(route('accounts.index'));

        $company = Company::where('tax_id', '1008')->firstOrFail();
        $this->assertSame($company->id, session('company_id'));
        $this->assertTrue($user->companies()->whereKey($company->id)->exists());
    }

    public function test_new_company_catalog_is_visible_immediately(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('companies.store'), [
                ...$this->companyPayload('Con Catálogo', '1009'),
                'create_default_catalog' => '1',
            ])
            ->assertRedirect(route('accounts.index'));

        $company = Company::where('tax_id', '1009')->firstOrFail();
        $this->assertDatabaseHas('accounts', [
            'company_id' => $company->id,
            'code' => '1.1.01',
            'name' => 'Caja General',
        ]);

        $this->get(route('accounts.index'))
            ->assertOk()
            ->assertSee('Caja General');
    }

    public function test_user_without_companies_can_access_dashboard_without_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['company_id' => 999999])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionMissing('company_id');

        $this->assertNull(app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    private function company(string $name, string $taxId, CompanyStatus $status = CompanyStatus::ACTIVE): Company
    {
        return Company::create([
            'name' => $name,
            'legal_name' => $name.' S.A.',
            'tax_id' => $taxId,
            'status' => $status,
        ]);
    }

    private function attach(User $user, Company $company): void
    {
        $user->companies()->attach($company->id, [
            'is_owner' => true,
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    private function companyPayload(string $name, string $taxId): array
    {
        return [
            'name' => $name,
            'legal_name' => $name.' S.A.',
            'tax_id' => $taxId,
        ];
    }
}
