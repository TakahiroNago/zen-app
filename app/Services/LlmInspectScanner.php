<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Asks llm-inspect-server, which listens on the loopback address of the same host, to scan a text.
 *
 * The server does not log request bodies. On this side, failures are reported by status only:
 * neither the request nor the response body is put into an exception message.
 */
class LlmInspectScanner implements PersonalInfoScanner
{
    public function scan(#[\SensitiveParameter] string $text): array
    {
        if ($text === '') {
            return [];
        }

        $url = config('services.llm_inspect.url');
        if (! is_string($url) || $url === '') {
            throw new PersonalInfoDetectionUnavailable('LLM_INSPECT_URL is not configured.');
        }

        $request = Http::baseUrl($url)
            ->acceptJson()
            ->connectTimeout((float) config('services.llm_inspect.connect_timeout'))
            ->timeout((float) config('services.llm_inspect.timeout'))
            ->retry(2, 100, throw: false);

        $token = config('services.llm_inspect.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post('/scan', ['text' => $text]);
        } catch (ConnectionException) {
            throw new PersonalInfoDetectionUnavailable('llm-inspect-server could not be reached.');
        }

        if (! $response->successful()) {
            throw new PersonalInfoDetectionUnavailable('llm-inspect-server answered with status '.$response->status().'.');
        }

        $findings = $response->json('findings');
        if (! is_array($findings)) {
            throw new PersonalInfoDetectionUnavailable('llm-inspect-server returned a report without findings.');
        }

        $length = strlen($text);
        $result = [];

        foreach ($findings as $finding) {
            $ruleId = $finding['rule_id'] ?? null;
            $start = $finding['start'] ?? null;
            $end = $finding['end'] ?? null;

            if (! is_string($ruleId) || ! is_int($start) || ! is_int($end) || $start < 0 || $end < $start || $end > $length) {
                throw new PersonalInfoDetectionUnavailable('llm-inspect-server returned a malformed finding.');
            }

            $result[] = ['rule_id' => $ruleId, 'start' => $start, 'end' => $end];
        }

        return $result;
    }
}
