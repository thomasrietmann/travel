<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Task;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;

class UpgradeCompatibilityTest extends TestCase
{
    public function test_additive_upgrade_preserves_existing_rows_passwords_tokens_and_files(): void
    {
        // Only the two NEW migrations are removed in this isolated test database.
        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();
        $owner = User::factory()->create(['countdown_share_token' => str_repeat('a', 48)]);
        $shared = User::factory()->create();
        $owner->emailAliases()->create(['email' => 'legacy-alias@example.com']);
        $trip = Trip::factory()->for($owner)->create();
        $trip->sharedUsers()->attach($shared);
        $booking = Booking::factory()->for($trip)->create();
        Task::factory()->for($trip)->create();
        $document = Document::factory()->for($trip)->for($booking)->create();
        Storage::disk('public')->put($document->file_path, 'existing file bytes');
        $tables = ['users', 'trips', 'trip_user', 'bookings', 'tasks', 'documents'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table);
        }
        $this->assertDatabaseHas('user_email_aliases', ['email' => 'legacy-alias@example.com', 'user_id' => $owner->id, 'verified_at' => null]);
        $this->assertSame('existing file bytes', Storage::disk('public')->get($document->file_path));
        $this->actingAs($shared)->get(route('documents.download', $document))->assertDownload();
    }

    public function test_key_pages_render_with_local_assets_and_security_headers(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->for($user)->create();
        $booking = Booking::factory()->for($trip)->create();
        $document = Document::factory()->for($trip)->create();
        $task = Task::factory()->for($trip)->create();
        $this->actingAs($user);
        foreach (['/dashboard', '/settings', '/countdown', '/documents', '/tasks', '/trips/create', '/trips/'.$trip->id, '/trips/'.$trip->id.'/edit', '/trips/'.$trip->id.'/bookings/create', '/bookings/'.$booking->id.'/edit', '/documents/'.$document->id.'/edit', '/tasks/'.$task->id.'/edit'] as $url) {
            $this->get($url)->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('build/app.css')->assertDontSee('cdn.tailwindcss.com')->assertDontSee('onsubmit=');
        }
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin')->assertOk();
        $this->get('/admin/mail-import')->assertOk();
        $this->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_shared_users_can_edit_but_only_owners_can_delete_or_manage_shares(): void
    {
        $trip = Trip::factory()->create();
        $shared = User::factory()->create();
        $trip->sharedUsers()->attach($shared);
        $this->actingAs($shared)->get('/trips/'.$trip->id.'/edit')->assertOk();
        $this->delete(route('trips.destroy', $trip))->assertForbidden();
        $this->post(route('trips.shares.store', $trip), ['email' => User::factory()->create()->email])->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('trips.show', $trip))->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_csrf_and_canonical_host_are_enforced_outside_test_bypass(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->post('/login', ['email' => 'someone@example.com', 'password' => 'wrong'])->assertStatus(419);
            $this->get('http://evil-localhost.example/login')->assertStatus(400);
        } finally {
            $this->app->instance('env', 'testing');
            Request::setTrustedHosts([]);
        }
    }
}
