<?php

namespace Tests\Feature\Notifications;

use App\Models\AccountStatus;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_notifications_endpoint_works_for_user_roles_and_is_scoped(): void
    {
        foreach (['candidate', 'company'] as $accountType) {
            $user = User::factory()->create(['account_type' => $accountType]);
            $notification = Notification::create([
                'recipient_id' => $user->id,
                'channel' => 'platform',
                'type' => 'system',
                'title' => ucfirst($accountType) . ' update',
                'body' => 'A notification for this account.',
                'delivery_status' => 'delivered',
            ]);

            $this->actingAs($user)
                ->getJson('/api/notifications')
                ->assertOk()
                ->assertJsonPath('data.0.id', $notification->id)
                ->assertJsonPath('data.0.is_read', false)
                ->assertJsonPath('meta.unread_count', 1);

            $this->actingAs($user)
                ->patchJson("/api/notifications/{$notification->id}/read")
                ->assertOk()
                ->assertJsonPath('data.is_read', true);
        }

        $owner = User::factory()->create();
        $foreign = Notification::create([
            'recipient_id' => $owner->id,
            'channel' => 'platform',
            'type' => 'system',
            'title' => 'Private',
            'body' => 'Private notification.',
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->patchJson("/api/notifications/{$foreign->id}/read")
            ->assertNotFound();
    }

    public function test_shared_notifications_endpoint_supports_platform_admin_recipients(): void
    {
        $admin = Admin::create([
            'name' => 'Platform Admin',
            'email' => 'notifications-admin@example.com',
            'password' => bcrypt('secret123'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
        $notification = Notification::create([
            'admin_recipient_id' => $admin->id,
            'channel' => 'platform',
            'type' => 'security',
            'title' => 'Security alert',
            'body' => 'Review the latest security event.',
            'delivery_status' => 'delivered',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.type', 'security');

        $this->actingAs($admin)
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_shared_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->patchJson('/api/notifications/1/read')->assertUnauthorized();
    }
}
