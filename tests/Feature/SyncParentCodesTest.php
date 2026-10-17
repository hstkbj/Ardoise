<?php

namespace Tests\Feature;

use App\Models\Central\ParentAccessCode;
use App\Models\Tenant\ParentProfile;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Synchronisation des codes parents d'une école absente de parent_access_codes. */
class SyncParentCodesTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_an_existing_code_is_put_back_and_the_parent_logs_in_with_it(): void
    {
        $this->createSchool();
        $code = $this->seedSchoolData()['parent_code'];
        ParentAccessCode::query()->delete();

        $this->postJson('/api/v1/parent/login', ['code' => $code])->assertUnprocessable();

        $this->artisan('parents:sync-codes', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, ParentAccessCode::count());

        $this->artisan('parents:sync-codes')->assertSuccessful();
        $this->artisan('parents:sync-codes')->assertSuccessful();

        $this->assertSame(1, ParentAccessCode::where('tenant_id', $this->tenant->id)->count());
        $this->postJson('/api/v1/parent/login', ['code' => $code])->assertOk();
    }

    public function test_parents_without_code_get_one_only_with_generate_and_disabled_parents_are_left_alone(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $this->inSchool(function () {
            ParentProfile::query()->update(['access_code' => null]);
            [$disabled] = app(AccountService::class)->createParent(['first_name' => 'Ancien', 'last_name' => 'Accès', 'phone' => '+22997000009']);
            $disabled->update(['status' => 'inactive']);
        });
        ParentAccessCode::query()->delete();

        $this->artisan('parents:sync-codes')->assertSuccessful();
        $this->assertSame(0, ParentAccessCode::count());

        $this->artisan('parents:sync-codes', ['--generate' => true])->assertSuccessful();

        $this->assertSame(1, ParentAccessCode::count());
        $code = $this->inSchool(fn () => ParentProfile::where('status', '!=', 'inactive')->first()->access_code);
        $this->postJson('/api/v1/parent/login', ['code' => $code])->assertOk();
    }
}
