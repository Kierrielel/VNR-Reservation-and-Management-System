<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_view_account_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.settings'));

        $response->assertStatus(200);
        $response->assertSee('Account Settings');
        $response->assertSee('Profile Information');
        $response->assertSee('Change Password');
    }

    public function test_guest_cannot_view_account_settings(): void
    {
        $response = $this->get(route('account.settings'));

        $response->assertRedirect(route('login'));
    }

    public function test_customer_can_update_profile(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->put(route('account.settings.update'), [
            'section' => 'profile',
            'name' => 'Updated Guest',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect(route('account.settings'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Guest',
            'email' => 'updated@example.com',
        ]);
        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }

    public function test_customer_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->put(route('account.settings.update'), [
            'section' => 'password',
            'current_password' => 'old-password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('account.settings'));
        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_customer_must_provide_the_current_password_to_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->from(route('account.settings'))->put(route('account.settings.update'), [
            'section' => 'password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
            'current_password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }
}
