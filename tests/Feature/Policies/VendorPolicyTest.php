<?php

namespace Tests\Feature\Policies;

use App\Authorization\Actor;
use App\Models\Vendor;
use App\Policies\VendorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPolicyTest extends TestCase
{
    use RefreshDatabase;

    private VendorPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new VendorPolicy;
    }

    public function test_any_authenticated_user_can_create_vendor(): void
    {
        $actor = new Actor('U_REGULAR_USER', false);

        $this->assertTrue($this->policy->create($actor));
    }

    public function test_admin_can_create_vendor(): void
    {
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->create($actor));
    }

    public function test_creator_can_update_own_vendor(): void
    {
        $creatorId = 'U_CREATOR';
        $vendor = Vendor::factory()->create([
            'created_by_provider_user_id' => $creatorId,
        ]);
        $actor = new Actor($creatorId, false);

        $this->assertTrue($this->policy->update($actor, $vendor));
    }

    public function test_non_creator_cannot_update_vendor(): void
    {
        $vendor = Vendor::factory()->create([
            'created_by_provider_user_id' => 'U_CREATOR',
        ]);
        $actor = new Actor('U_OTHER_USER', false);

        $this->assertFalse($this->policy->update($actor, $vendor));
    }

    public function test_admin_can_update_any_vendor(): void
    {
        $vendor = Vendor::factory()->create([
            'created_by_provider_user_id' => 'U_CREATOR',
        ]);
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->update($actor, $vendor));
    }

    public function test_creator_can_deactivate_own_vendor(): void
    {
        $creatorId = 'U_CREATOR';
        $vendor = Vendor::factory()->create([
            'created_by_provider_user_id' => $creatorId,
        ]);
        $actor = new Actor($creatorId, false);

        $this->assertTrue($this->policy->deactivate($actor, $vendor));
    }

    public function test_non_creator_cannot_deactivate_vendor(): void
    {
        $vendor = Vendor::factory()->create([
            'created_by_provider_user_id' => 'U_CREATOR',
        ]);
        $actor = new Actor('U_OTHER_USER', false);

        $this->assertFalse($this->policy->deactivate($actor, $vendor));
    }

    public function test_admin_can_deactivate_any_vendor(): void
    {
        $vendor = Vendor::factory()->create([
            'created_by_provider_user_id' => 'U_CREATOR',
        ]);
        $actor = new Actor('U_ADMIN', true);

        $this->assertTrue($this->policy->deactivate($actor, $vendor));
    }
}
