<?php

namespace Tests\Feature\Auth;

use App\Models\Organisation;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'stranger@example.test'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_invitation_registers_only_the_invited_address_and_requires_verification(): void
    {
        $company = Organisation::create(['name' => 'Client']);
        $invite = app(MembershipService::class)->invite($company, 'invited@example.test', 'viewer');
        $path = parse_url($invite, PHP_URL_PATH);
        $this->get($path)->assertRedirect('/register');
        $this->get('/register')->assertOk();
        $this->post('/register', [
            'name' => 'Invited User',
            'email' => 'different@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/verify-email');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'invited@example.test', 'email_verified_at' => null]);
        $this->assertDatabaseMissing('users', ['email' => 'different@example.test']);
        $this->post($path)->assertRedirect('/verify-email');
        $this->assertCount(0, $company->members()->get());
    }
}
