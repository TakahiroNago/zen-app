<?php

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

const SENSITIVE_CONCERN = '秘密の相談内容です。誰にも知られたくない悩みがあります';

const SENSITIVE_ANSWER = '秘密の回答本文です。ゆっくり呼吸を整えましょう';

beforeEach(function () {
    $this->logPath = tempnam(sys_get_temp_dir(), 'zen-log-');
    config([
        'logging.default' => 'sensitive_data_test',
        'logging.channels.sensitive_data_test' => ['driver' => 'single', 'path' => $this->logPath, 'level' => 'debug'],
    ]);
});

afterEach(function () {
    @unlink($this->logPath);
});

/**
 * Every fragment that could identify the consultation or answer text. PHP prints the first
 * 15 bytes of string arguments in traces with non-ASCII bytes escaped as `\xHH`, and the log
 * formatter may turn the backslashes into slashes, so those encodings are included too.
 *
 * @return list<string>
 */
function sensitiveFragments(): array
{
    $fragments = [];
    foreach ([SENSITIVE_CONCERN, SENSITIVE_ANSWER] as $text) {
        $escapedPrefix = implode('', array_map(
            fn (string $byte): string => sprintf('\\x%02X', ord($byte)),
            str_split(substr($text, 0, 6)),
        ));

        $fragments[] = mb_substr($text, 0, 5);
        $fragments[] = $escapedPrefix;
        $fragments[] = str_replace('\\', '/', $escapedPrefix);
    }

    return $fragments;
}

test('exception traces never capture function arguments', function () {
    $throwWithArgument = function (string $concern): never {
        throw new RuntimeException('failed');
    };

    try {
        $throwWithArgument(SENSITIVE_CONCERN);
    } catch (RuntimeException $exception) {
        expect(ini_get('zend.exception_ignore_args'))->toBe('1')
            ->and($exception->getTrace()[0])->not->toHaveKey('args')
            ->and($exception->getTraceAsString())->not->toContain(mb_substr(SENSITIVE_CONCERN, 0, 5));
    }
});

test('a failing OpenAI consultation logs the exception without the consultation text', function () {
    OpenAI::fake([CreateResponse::fake(['choices' => [['message' => ['content' => '']]]])]);

    $response = $this->actingAs(User::factory()->create())
        ->post(route('zen.respond'), ['concern' => SENSITIVE_CONCERN])
        ->assertServerError();

    $log = file_get_contents($this->logPath);
    expect($log)->toContain('UnexpectedValueException')
        ->toContain('OpenAI returned no consultation response.')
        ->toContain('[stacktrace]')
        ->toContain('ZenController->respondWithLock(')
        ->toContain('"userId":')
        ->toContain('"request_id":"'.$response->headers->get(AssignRequestId::HEADER).'"');
    foreach (sensitiveFragments() as $fragment) {
        expect($log)->not->toContain($fragment);
    }
});

test('a failing consultation insert logs neither the consultation text nor the query bindings', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => SENSITIVE_ANSWER]]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => '{"topic":"仕事","summary":"要約です。"}']]]]),
    ]);
    $reportedBindings = null;
    app(ExceptionHandler::class)->reportable(function (QueryException $exception) use (&$reportedBindings): void {
        $reportedBindings = $exception->getBindings();
    });
    Schema::table('zen_consultations', fn ($table) => $table->dropColumn('image_number'));

    $this->actingAs(User::factory()->create())
        ->post(route('zen.respond'), ['concern' => SENSITIVE_CONCERN])
        ->assertServerError();

    $log = file_get_contents($this->logPath);
    expect($log)->toContain('QueryException')
        ->toContain('insert into "zen_consultations"')
        ->and($reportedBindings)->toBe([]);
    foreach (sensitiveFragments() as $fragment) {
        expect($log)->not->toContain($fragment);
    }
});

test('query exception messages mask their bindings', function () {
    try {
        DB::table('zen_consultations')->insert(['concern' => SENSITIVE_CONCERN, 'missing_column' => 1]);
    } catch (QueryException $exception) {
        expect($exception->getMessage())->not->toContain(mb_substr(SENSITIVE_CONCERN, 0, 5));

        report($exception);
    }

    expect(file_get_contents($this->logPath))->toContain('QueryException')
        ->not->toContain(mb_substr(SENSITIVE_CONCERN, 0, 5));
});

test('every response carries a server generated request id', function () {
    $first = $this->withHeader(AssignRequestId::HEADER, 'client-supplied')->get(route('home'));
    $second = $this->get(route('home'));

    expect($first->headers->get(AssignRequestId::HEADER))->toMatch('/^[0-9a-f-]{36}$/')
        ->and($second->headers->get(AssignRequestId::HEADER))->not->toBe($first->headers->get(AssignRequestId::HEADER));
});

test('sessions holding consultations are stored encrypted', function () {
    config(['session.driver' => 'database']);
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => SENSITIVE_ANSWER]]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => '{"topic":"仕事","summary":"要約です。"}']]]]),
    ]);

    $this->post(route('zen.respond'), ['concern' => SENSITIVE_CONCERN])->assertOk();

    $payload = DB::table('sessions')->sole()->payload;
    expect((bool) config('session.encrypt'))->toBeTrue()
        ->and(base64_decode($payload))->not->toContain(SENSITIVE_CONCERN)
        ->and(base64_decode($payload))->not->toContain('zen_consultations')
        ->and(session('zen_consultations.0.concern'))->toBe(SENSITIVE_CONCERN);

    $this->get(route('consultations.show', 'session-0'))
        ->assertOk()
        ->assertSee(SENSITIVE_ANSWER);
});

test('pages only allow images from this site', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "img-src 'self' data:");
});

test('the vite dev server may serve images while running hot', function () {
    $hotFile = storage_path('framework/testing-vite.hot');
    file_put_contents($hotFile, "http://[::1]:5173/\n");
    Vite::useHotFile($hotFile);

    try {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "img-src 'self' data: http://[::1]:5173");
    } finally {
        @unlink($hotFile);
    }
});
