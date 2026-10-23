<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('serves a lightweight liveness endpoint', function () {
    $this->get('/up')->assertOk();
});

it('reports ready when the database and queue storage are available', function () {
    config()->set('queue.default', 'database');

    $this->get(route('readiness'))
        ->assertOk()
        ->assertExactJson([
            'status' => 'ready',
            'checks' => ['database' => 'ok', 'queue' => 'ok'],
        ]);
});

it('reports not ready without exposing exception details', function () {
    Log::spy();
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.table', 'missing_jobs_table');

    $this->get(route('readiness'))
        ->assertServiceUnavailable()
        ->assertExactJson([
            'status' => 'not_ready',
            'checks' => ['database' => 'unavailable', 'queue' => 'unavailable'],
        ])
        ->assertDontSee('RuntimeException');

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'Readiness check failed.'
            && $context['exception_class'] === RuntimeException::class,
    );
});

it('adds a correlation identifier to responses', function () {
    $requestId = (string) Str::uuid();

    $this->withHeader('X-Request-ID', $requestId)
        ->get('/up')
        ->assertHeader('X-Request-ID', $requestId);

    $generatedRequestId = $this->withHeader('X-Request-ID', 'untrusted-value')
        ->get('/up')
        ->headers->get('X-Request-ID');

    expect($generatedRequestId)->not->toBe('untrusted-value')
        ->and(Str::isUuid($generatedRequestId))->toBeTrue();
});

it('clears authenticated log context before the next anonymous request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    expect(Log::sharedContext()['user_id'] ?? null)->toBe($user->id);

    auth()->logout();
    $this->get('/up')->assertOk();
    expect(Log::sharedContext())->not->toHaveKey('user_id');
});
