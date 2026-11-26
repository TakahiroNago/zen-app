<?php

namespace Tests\Support;

use App\Services\PersonalInfoDetectionUnavailable;
use App\Services\PersonalInfoScanner;

/**
 * Stands in for llm-inspect-server in tests. It has no rules of its own: a test states which
 * pieces of text the scanner will report, and under which llm-inspect rule id.
 *
 * What the real rules detect is tested in llm-inspect, and against a running server in
 * tests/Integration/LlmInspectContractTest.php.
 */
class FakePersonalInfoScanner implements PersonalInfoScanner
{
    /**
     * @var array<string, string>
     */
    private array $flagged = [];

    private bool $unavailable = false;

    /**
     * Every occurrence of $text will be reported as a finding of $ruleId.
     */
    public function flag(string $text, string $ruleId): static
    {
        $this->flagged[$text] = $ruleId;

        return $this;
    }

    /**
     * Behaves as if the server could not be reached.
     */
    public function unavailable(bool $unavailable = true): static
    {
        $this->unavailable = $unavailable;

        return $this;
    }

    public function scan(#[\SensitiveParameter] string $text): array
    {
        if ($this->unavailable) {
            throw new PersonalInfoDetectionUnavailable('The fake scanner was told to be unavailable.');
        }

        $findings = [];

        foreach ($this->flagged as $needle => $ruleId) {
            $offset = 0;

            while (($start = strpos($text, $needle, $offset)) !== false) {
                $offset = $start + strlen($needle);
                $findings[] = ['rule_id' => $ruleId, 'start' => $start, 'end' => $offset];
            }
        }

        return $findings;
    }
}
