<?php

namespace Tests\Feature\Contact;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_send_name_and_email_and_message_is_stored(): void
    {
        $response = $this->postJson('/api/contact', [
            'title' => 'Cannot register',
            'description' => 'The registration page returns an error.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        $this->postJson('/api/contact', [
            'title' => 'Cannot register',
            'description' => 'The registration page returns an error.',
            'name' => 'Guest User',
            'email' => 'guest@example.com',
        ])->assertCreated()->assertJsonPath('data.user_id', null);

        $this->assertDatabaseHas('contact_messages', [
            'title' => 'Cannot register',
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'user_id' => null,
        ]);
    }

    public function test_authenticated_user_can_send_contact_details_and_is_linked_to_users_table(): void
    {
        $user = User::factory()->create(['name' => 'Registered User', 'email' => 'registered@example.com']);

        $this->actingAs($user)->postJson('/api/contact', [
            'title' => 'Help me sign in',
            'description' => 'I cannot access my dashboard.',
            'name' => 'Registered User',
            'email' => 'registered@example.com',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Registered User')
            ->assertJsonPath('data.email', 'registered@example.com')
            ->assertJsonPath('data.user_id', $user->id);
    }

    public function test_only_super_admin_can_manage_contact_messages(): void
    {
        ContactMessage::create([
            'title' => 'Need help',
            'description' => 'Please help me complete registration.',
            'name' => 'Guest',
            'email' => 'guest@example.com',
        ]);
        $regularAdmin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'account_status_id' => 1]);
        $superAdmin = Admin::create(['name' => 'Super Admin', 'email' => 'super@example.com', 'password' => 'password', 'account_status_id' => 1]);
        $role = AdminRole::create(['name' => 'super_admin', 'scope' => 'platform']);
        $superAdmin->roles()->attach($role->id);

        $this->actingAs($regularAdmin)->getJson('/api/super-admin/contact-messages')->assertForbidden();

        $this->actingAs($superAdmin)->getJson('/api/super-admin/contact-messages')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Need help');

        $message = ContactMessage::first();
        $this->actingAs($superAdmin)->patchJson("/api/super-admin/contact-messages/{$message->id}/status", [
            'status' => 'in_progress',
            'admin_note' => 'We will contact the visitor.',
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->actingAs($superAdmin)->deleteJson("/api/super-admin/contact-messages/{$message->id}")
            ->assertOk();
        $this->assertSoftDeleted('contact_messages', ['id' => $message->id]);
    }
}
