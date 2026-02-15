<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Actor;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\TestCase;

class ActorTest extends TestCase
{
    public function test_actor_implements_authenticatable(): void
    {
        $actor = new Actor('U123');

        $this->assertInstanceOf(Authenticatable::class, $actor);
    }

    public function test_constructor_sets_provider_user_id(): void
    {
        $actor = new Actor('U_TEST_123');

        $this->assertEquals('U_TEST_123', $actor->providerUserId);
    }

    public function test_constructor_defaults_is_admin_to_false(): void
    {
        $actor = new Actor('U123');

        $this->assertFalse($actor->isAdmin);
    }

    public function test_constructor_sets_is_admin_when_provided(): void
    {
        $actor = new Actor('U123', true);

        $this->assertTrue($actor->isAdmin);
    }

    public function test_get_auth_identifier_name_returns_provider_user_id(): void
    {
        $actor = new Actor('U123');

        $this->assertEquals('provider_user_id', $actor->getAuthIdentifierName());
    }

    public function test_get_auth_identifier_returns_user_id(): void
    {
        $actor = new Actor('U_MY_ID');

        $this->assertEquals('U_MY_ID', $actor->getAuthIdentifier());
    }

    public function test_get_auth_password_returns_empty_string(): void
    {
        $actor = new Actor('U123');

        $this->assertEquals('', $actor->getAuthPassword());
    }

    public function test_get_remember_token_returns_empty_string(): void
    {
        $actor = new Actor('U123');

        $this->assertEquals('', $actor->getRememberToken());
    }

    public function test_set_remember_token_is_noop(): void
    {
        $actor = new Actor('U123');
        $actor->setRememberToken('some_value');

        $this->assertEquals('', $actor->getRememberToken());
    }

    public function test_get_auth_password_name_returns_empty_string(): void
    {
        $actor = new Actor('U123');

        $this->assertEquals('', $actor->getAuthPasswordName());
    }

    public function test_get_remember_token_name_returns_empty_string(): void
    {
        $actor = new Actor('U123');

        $this->assertEquals('', $actor->getRememberTokenName());
    }
}
