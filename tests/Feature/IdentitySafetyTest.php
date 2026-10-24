<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\VerifyAlias;
use App\Services\EmailIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdentitySafetyTest extends TestCase
{
    public function test_registration_cannot_claim_an_existing_alias_even_with_different_case(): void
    {
        $owner = User::factory()->create();
        $owner->emailAliases()->create(['email' => 'victim@example.com']);
        $this->post('/register', ['name' => 'Someone', 'email' => 'VICTIM@example.com', 'password' => 'secure-password', 'password_confirmation' => 'secure-password'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('user_email_aliases', ['user_id' => $owner->id, 'email' => 'victim@example.com']);
    }

    public function test_shared_identity_writer_checks_both_tables_inside_transaction(): void
    {
        $owner = User::factory()->create(['email' => 'claimed@example.com']);
        $this->expectException(ValidationException::class);
        app(EmailIdentity::class)->create('CLAIMED@example.com', fn ($email) => $owner->emailAliases()->create(['email' => $email]));
    }

    public function test_existing_unverified_bcrypt_login_preserves_hash_and_access(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $hash = $user->password;
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($hash, $user->fresh()->password);
        $this->get('/dashboard')->assertOk();
        $this->assertNull(app(EmailIdentity::class)->verifiedUser($user->email));
    }

    public function test_login_attempts_are_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'unknown@example.com', 'password' => 'incorrect'])->assertRedirect();
        }
        $this->post('/login', ['email' => 'UNKNOWN@example.com', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_new_registration_regenerates_session_and_does_not_need_smtp(): void
    {
        $this->get('/register');
        $before = session()->getId();
        $this->post('/register', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'secure-password', 'password_confirmation' => 'secure-password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertNotSame($before, session()->getId());
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'email_verified_at' => null, 'is_admin' => 0]);
    }

    public function test_alias_verification_requires_signed_link_and_correct_account(): void
    {
        $user = User::factory()->create();
        $alias = $user->emailAliases()->create(['email' => 'alias@example.com']);
        Notification::fake();
        $this->actingAs($user)->post(route('settings.email-aliases.send', $alias))->assertRedirect();
        Notification::assertSentOnDemand(VerifyAlias::class);
        $link = URL::temporarySignedRoute('settings.email-aliases.verify', now()->addHour(), ['alias' => $alias->id, 'hash' => sha1($alias->email)]);
        $this->actingAs(User::factory()->create())->get($link)->assertForbidden();
        $this->actingAs($user)->get($link.'&tampered=1')->assertForbidden();
        $this->get($link)->assertRedirect(route('settings.index'));
        $this->assertNotNull($alias->fresh()->verified_at);
        $this->assertSame($user->id, app(EmailIdentity::class)->verifiedUser($alias->email)->id);
    }

    public function test_ambiguous_legacy_address_never_selects_an_owner(): void
    {
        $user = User::factory()->create();
        $alias = $user->emailAliases()->create(['email' => 'collision@example.com']);
        $alias->forceFill(['verified_at' => now()])->save();
        User::factory()->create(['email' => 'collision@example.com']);
        $this->assertNull(app(EmailIdentity::class)->verifiedUser('collision@example.com'));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_countdown_is_opt_in_and_can_be_rotated_and_revoked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/settings')->assertOk();
        $this->assertNull($user->fresh()->countdown_share_token);
        $this->post(route('settings.countdown.rotate'))->assertRedirect();
        $old = $user->fresh()->countdown_share_token;
        $this->get(route('countdown.public', $old))->assertOk();
        $this->post(route('settings.countdown.rotate'))->assertRedirect();
        $this->get(route('countdown.public', $old))->assertNotFound();
        $new = $user->fresh()->countdown_share_token;
        $this->delete(route('settings.countdown.revoke'))->assertRedirect();
        $this->get(route('countdown.public', $new))->assertNotFound();
    }

    public function test_main_address_verification_is_signed_and_session_bound(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($link)->assertRedirect('/settings');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_password_remediation_preserves_account_and_documents_and_revokes_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['is_admin' => true]);
        $trip = Trip::factory()->for($user)->create();
        $document = Document::factory()->for($trip)->create();
        DB::table('sessions')->insert(['id' => 'existing-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->artisan('account:password', ['email' => $user->email])
            ->expectsQuestion('Neues Passwort (mindestens 12 Zeichen)', 'new-safe-password')
            ->expectsQuestion('Passwort wiederholen', 'new-safe-password')->assertSuccessful();
        $this->assertTrue(Hash::check('new-safe-password', $user->fresh()->password));
        $this->assertSame($user->id, $trip->fresh()->user_id);
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'existing-session']);
    }
}
