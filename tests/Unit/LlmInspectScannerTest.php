<?php

use App\Services\LlmInspectScanner;
use App\Services\PersonalInfoDetectionUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.llm_inspect.url' => 'http://127.0.0.1:8787',
        'services.llm_inspect.token' => null,
        'services.llm_inspect.connect_timeout' => 1,
        'services.llm_inspect.timeout' => 3,
    ]);
    Http::preventStrayRequests();
});

function scanReport(array $findings): array
{
    return ['decision' => 'warn', 'findings' => $findings, 'normalized' => false, 'removed_invisible' => 0];
}

test('it posts the text to /scan and returns the rule id and byte range of each finding', function () {
    Http::fake(['127.0.0.1:8787/scan' => Http::response(scanReport([
        ['rule_id' => 'pii.email', 'category' => 'pii', 'severity' => 'medium', 'start' => 10, 'end' => 26, 'normalized_only' => false],
    ]))]);

    $findings = (new LlmInspectScanner)->scan('連絡は boss@example.com');

    expect($findings)->toBe([['rule_id' => 'pii.email', 'start' => 10, 'end' => 26]]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://127.0.0.1:8787/scan'
        && $request->data() === ['text' => '連絡は boss@example.com']
        && ! $request->hasHeader('Authorization'));
});

test('it sends the bearer token when one is configured', function () {
    config(['services.llm_inspect.token' => 'secret-token']);
    Http::fake(['127.0.0.1:8787/scan' => Http::response(scanReport([]))]);

    expect((new LlmInspectScanner)->scan('text'))->toBe([]);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
});

test('an empty text is not sent', function () {
    Http::fake();

    expect((new LlmInspectScanner)->scan(''))->toBe([]);
    Http::assertNothingSent();
});

test('a server that cannot be reached makes detection unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    (new LlmInspectScanner)->scan('boss@example.com');
})->throws(PersonalInfoDetectionUnavailable::class);

test('an error status makes detection unavailable', function (int $status) {
    Http::fake(['127.0.0.1:8787/scan' => Http::response(['error' => 'no'], $status)]);

    (new LlmInspectScanner)->scan('boss@example.com');
})->with([401, 408, 413, 500, 503])->throws(PersonalInfoDetectionUnavailable::class);

test('a response that is not a scan report makes detection unavailable', function (mixed $body) {
    Http::fake(['127.0.0.1:8787/scan' => Http::response($body)]);

    (new LlmInspectScanner)->scan('boss@example.com');
})->with([
    'no findings key' => [['decision' => 'allow']],
    'findings is not a list' => [['findings' => 'none']],
    'finding without offsets' => [['findings' => [['rule_id' => 'pii.email']]]],
    'range past the end of the text' => [['findings' => [['rule_id' => 'pii.email', 'start' => 0, 'end' => 999]]]],
    'reversed range' => [['findings' => [['rule_id' => 'pii.email', 'start' => 5, 'end' => 2]]]],
    'not json' => ['<html>bad gateway</html>'],
])->throws(PersonalInfoDetectionUnavailable::class);

test('a missing url makes detection unavailable instead of skipping the check', function () {
    config(['services.llm_inspect.url' => '']);
    Http::fake();

    (new LlmInspectScanner)->scan('boss@example.com');
})->throws(PersonalInfoDetectionUnavailable::class);

test('the failure message never contains the scanned text', function () {
    Http::fake(['127.0.0.1:8787/scan' => Http::response('echo: boss@example.com', 500)]);

    try {
        (new LlmInspectScanner)->scan('boss@example.com');
    } catch (PersonalInfoDetectionUnavailable $exception) {
        expect($exception->getMessage())->not->toContain('boss@example.com');

        return;
    }

    $this->fail('Detection should have been unavailable.');
});
