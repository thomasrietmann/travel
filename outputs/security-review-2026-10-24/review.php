<?php
declare(strict_types=1);

// Isolated review only: synthetic users, in-memory SQLite, no external requests.
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'BCRYPT_ROUNDS' => '4',
    'OPENAI_API_KEY' => '', 'MAIL_IMPORT_ENABLED' => 'false',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require __DIR__.'/vendor/autoload.php';
$loader = new Composer\Autoload\ClassLoader();
$loader->addPsr4('App\\', __DIR__.'/app');
$loader->addPsr4('Database\\Factories\\', __DIR__.'/database/factories');
$loader->register(true);
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

use App\Models\User;
use App\Models\Trip;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

$checks = [];
function record(string $name, mixed $evidence): void {
    global $checks;
    $checks[$name] = $evidence;
}
function requestAs(?User $user, string $method, string $url, array $data = [], array $files = []): Symfony\Component\HttpFoundation\Response {
    global $app;
    $app['auth']->forgetGuards();
    if ($user) {
        Auth::guard('web')->setUser($user);
    }
    $request = Illuminate\Http\Request::create($url, $method, $data, [], $files, ['HTTP_ACCEPT' => 'text/html']);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    return $response;
}
function invokePrivate(object $object, string $method, mixed ...$args): mixed {
    return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
}
$owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'test-password']);
try {
    Illuminate\Support\Facades\Hash::driver('argon2id')->check('test-password', $owner->password);
    record('direct_argon_switch_with_existing_bcrypt', 'accepted');
} catch (Throwable $e) {
    record('direct_argon_switch_with_existing_bcrypt', $e::class.': '.$e->getMessage());
}
$shared = User::create(['name' => 'Shared', 'email' => 'shared@example.test', 'password' => 'test-password']);
$outsider = User::create(['name' => 'Outsider', 'email' => 'outsider@example.test', 'password' => 'test-password']);
$admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'test-password', 'is_admin' => true]);
$trip = $owner->trips()->create(['title' => 'Synthetic Trip', 'type' => 'other', 'status' => 'planned', 'start_date' => '2030-06-01']);
$trip->sharedUsers()->attach($shared);
Storage::disk('local')->put('documents/review.pdf', 'SYNTHETIC DOCUMENT');
$document = $trip->documents()->create(['title' => 'Synthetic', 'document_type' => 'other', 'file_path' => 'documents/review.pdf']);

$routeActions = [];
foreach ($app['router']->getRoutes() as $route) {
    $action = $route->getActionName();
    if (! str_contains($action, '@')) { continue; }
    [$class, $method] = explode('@', $action, 2);
    if (! method_exists($class, $method)) {
        $routeActions[] = ['uri' => $route->uri(), 'action' => $action];
    }
}
record('missing_controller_actions', $routeActions);
$pages = [];
foreach (['/dashboard','/tasks','/documents','/settings','/countdown','/trips/'.$trip->id,'/trips/'.$trip->id.'/edit','/trips/'.$trip->id.'/bookings/create','/trips/'.$trip->id.'/documents/create','/trips/'.$trip->id.'/tasks/create'] as $url) {
    $pages[$url] = requestAs($owner, 'GET', $url)->getStatusCode();
}
record('authenticated_page_statuses', $pages);
record('access_boundaries', [
    'outsider_trip' => requestAs($outsider, 'GET', '/trips/'.$trip->id)->getStatusCode(),
    'outsider_document' => requestAs($outsider, 'GET', '/documents/'.$document->id.'/download')->getStatusCode(),
    'shared_document' => requestAs($shared, 'GET', '/documents/'.$document->id.'/download')->getStatusCode(),
    'user_admin_page' => requestAs($owner, 'GET', '/admin')->getStatusCode(),
    'admin_admin_page' => requestAs($admin, 'GET', '/admin')->getStatusCode(),
    'guest_document' => requestAs(null, 'GET', '/documents/'.$document->id.'/download')->getStatusCode(),
]);

$statuses = [];
for ($i = 0; $i < 8; $i++) {
    $statuses[] = requestAs(null, 'POST', '/login', ['email' => $owner->email, 'password' => 'wrong'])->getStatusCode();
}
record('eight_invalid_logins', $statuses);

requestAs($owner, 'POST', '/settings/email-aliases', ['email' => 'unverified@example.test']);
$importer = $app->make(App\Services\IncomingMailImporter::class);
record('alias_accepted_without_verification', invokePrivate($importer, 'findUserBySenders', ['unverified@example.test'])?->id === $owner->id);
$headers = (object) [
    'from' => [(object) ['mailbox' => 'unknown', 'host' => 'example.test']],
    'reply_to' => [(object) ['mailbox' => 'owner', 'host' => 'example.test']],
];
$senders = invokePrivate($importer, 'senderEmails', $headers);
record('reply_to_selects_victim_account', invokePrivate($importer, 'findUserBySenders', $senders)?->id === $owner->id);
requestAs(null, 'POST', '/register', ['name' => 'New Account', 'email' => 'unverified@example.test', 'password' => 'test-password', 'password_confirmation' => 'test-password']);
$newAccount = User::where('email', 'unverified@example.test')->first();
record('registration_overrides_existing_alias', [
    'account_created' => $newAccount !== null,
    'email_verified' => $newAccount?->email_verified_at !== null,
    'import_now_maps_to_new_account' => $newAccount && invokePrivate($importer, 'findUserBySenders', ['unverified@example.test'])?->id === $newAccount->id,
]);

record('attachment_accepts_executable_name_with_claimed_pdf_mime', invokePrivate($importer, 'isSupportedAttachment', 'example.php', 'application/pdf'));
record('attachment_accepts_pdf_name_with_non_pdf_mime', invokePrivate($importer, 'isSupportedAttachment', 'example.pdf', 'text/plain'));

eval('namespace App\\Services; function imap_headerinfo($imap, $number) { return (object) []; } function imap_fetchheader($imap, $number) { return "Message-ID: <synthetic-review@example.test>\\r\\n"; } function imap_setflag_full($imap, $number, $flag) { return true; }');
$activeImport = App\Models\ImportedMail::create(['message_id' => '<synthetic-review@example.test>', 'status' => 'processing']);
record('second_import_marks_active_job_failed', ['result' => invokePrivate($importer, 'importMessage', null, 1), 'status' => $activeImport->fresh()->status]);
record('failed_import_is_not_retried', invokePrivate($importer, 'importMessage', null, 1));

$owner->birthdays()->create(['name' => 'Synthetic Birthday', 'birth_date' => '2000-05-12']);
$token = $owner->ensureCountdownShareToken();
$public = requestAs(null, 'GET', '/shared/countdown/'.$token);
record('public_countdown', ['status' => $public->getStatusCode(), 'includes_birthday' => str_contains($public->getContent(), 'Synthetic Birthday'), 'cache_control' => $public->headers->get('Cache-Control'), 'referrer_policy' => $public->headers->get('Referrer-Policy'), 'csp' => $public->headers->get('Content-Security-Policy')]);

$deletionTrip = $owner->trips()->create(['title' => 'Deletion Test', 'type' => 'other', 'status' => 'planned']);
Storage::disk('local')->put('documents/orphan.pdf', 'SYNTHETIC DOCUMENT');
$deletionDoc = $deletionTrip->documents()->create(['title' => 'Orphan', 'file_path' => 'documents/orphan.pdf', 'document_type' => 'other']);
requestAs($owner, 'DELETE', '/trips/'.$deletionTrip->id);
record('trip_delete_leaves_file', ['document_record_exists' => Document::whereKey($deletionDoc->id)->exists(), 'file_exists' => Storage::disk('local')->exists('documents/orphan.pdf')]);

$fallbackRoot = __DIR__.'/separate-cron-storage';
config(['filesystems.private_fallback_roots' => [$fallbackRoot]]);
$path = 'documents/duplicate.pdf';
$app->make(App\Services\DocumentStorage::class)->writePrivate($path, 'SYNTHETIC DOCUMENT');
$duplicateDoc = $trip->documents()->create(['title' => 'Duplicate', 'file_path' => $path, 'document_type' => 'other']);
requestAs($owner, 'DELETE', '/documents/'.$duplicateDoc->id);
record('document_delete_leaves_fallback_copy', ['primary_exists' => Storage::disk('local')->exists($path), 'fallback_exists' => is_file($fallbackRoot.'/'.$path)]);

// Use fake failed writes only against synthetic local review documents.
$actualLocal = Storage::disk('local');
$actualPublic = Storage::disk('public');
$actualPublic->put('documents/migration.pdf', 'SYNTHETIC LEGACY DOCUMENT');
$trip->documents()->create(['title' => 'Migration', 'file_path' => 'documents/migration.pdf', 'document_type' => 'other']);
$failedDisk = Mockery::mock();
$failedDisk->shouldReceive('exists')->andReturn(false);
$failedDisk->shouldReceive('put')->andReturn(false);
Storage::set('local', $failedDisk);
$migrationStatus = Illuminate\Support\Facades\Artisan::call('documents:migrate-private');
record('migration_after_failed_write', ['exit_code' => $migrationStatus, 'source_still_exists' => $actualPublic->exists('documents/migration.pdf'), 'output' => trim(Illuminate\Support\Facades\Artisan::output())]);
Storage::set('local', $actualLocal);

$actualPublic->put('documents/review.pdf', 'SYNTHETIC DOCUMENT');
Illuminate\Support\Facades\Artisan::call('documents:migrate-private');
record('migration_leaves_public_duplicate_when_private_exists', $actualPublic->exists('documents/review.pdf'));

$replacePath = 'documents/replace.pdf';
$actualLocal->put($replacePath, 'SYNTHETIC ORIGINAL');
$replaceDoc = $trip->documents()->create(['title' => 'Replace', 'file_path' => $replacePath, 'document_type' => 'other']);
$failedDisk = Mockery::mock();
$failedDisk->shouldReceive('exists')->andReturnUsing(fn ($p) => $actualLocal->exists($p));
$failedDisk->shouldReceive('delete')->andReturnUsing(fn ($p) => $actualLocal->delete($p));
$failedDisk->shouldReceive('putFileAs')->andReturn(false);
Storage::set('local', $failedDisk);
$replaceResponse = requestAs($owner, 'POST', '/documents/'.$replaceDoc->id, ['_method' => 'PUT', 'title' => 'Replace', 'document_type' => 'other'], ['file' => Illuminate\Http\UploadedFile::fake()->create('new.pdf', 1, 'application/pdf')]);
record('replace_after_failed_write', ['status' => $replaceResponse->getStatusCode(), 'original_still_exists' => $actualLocal->exists($replacePath), 'database_file_path' => $replaceDoc->fresh()?->file_path]);
Storage::set('local', $actualLocal);

$viewErrors = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/resources/views')) as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) { continue; }
    try { $app['blade.compiler']->compile($file->getPathname()); }
    catch (Throwable $e) { $viewErrors[] = [$file->getPathname(), $e::class]; }
}
record('blade_compilation_errors', $viewErrors);
record('framework_used_for_isolated_review', Illuminate\Foundation\Application::VERSION);
record('loaded_application_source', (new ReflectionClass(App\Models\User::class))->getFileName());
putenv('TRIPCONTROL_AUDIT_EMPTY=');
record('laravel_empty_env_does_not_use_default', env('TRIPCONTROL_AUDIT_EMPTY', 'expected-default'));
foreach (['VIEW_PATH', 'VIEW_COMPILED_PATH', 'FILESYSTEM_LOCAL_ROOT'] as $key) {
    putenv($key.'=');
}
$exampleView = require __DIR__.'/config/view.php';
$exampleFiles = require __DIR__.'/config/filesystems.php';
record('blank_optional_env_configuration', ['view_paths' => $exampleView['paths'], 'view_compiled_path' => $exampleView['compiled'], 'local_storage_root' => $exampleFiles['disks']['local']['root']]);
echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
Mockery::close();
