<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_an_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $announcement = Announcement::create([
            'title' => 'Original title',
            'message' => 'Original message',
            'category' => 'General',
            'publish_at' => now(),
        ]);
        DB::table('announcement_reads')->insert([
            'user_id' => $admin->id,
            'announcement_id' => $announcement->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.announcements.index'))
            ->put(route('admin.announcements.update', $announcement), [
                'title' => 'Updated title',
                'message' => 'Updated message',
                'category' => 'Event',
                'publish_at' => now()->format('Y-m-d H:i:s'),
                'is_high_priority' => '1',
            ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Updated title',
            'message' => 'Updated message',
            'category' => 'Event',
            'is_high_priority' => true,
        ]);
        $this->assertDatabaseMissing('announcement_reads', [
            'announcement_id' => $announcement->id,
        ]);
    }

    public function test_non_admin_cannot_edit_an_announcement(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $announcement = Announcement::create([
            'title' => 'Original title',
            'message' => 'Original message',
            'category' => 'General',
            'publish_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Updated title',
            'message' => 'Updated message',
            'category' => 'Event',
            'publish_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Original title',
        ]);
    }
}
