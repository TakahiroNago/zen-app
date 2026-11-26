<?php

namespace App\Services;

use Illuminate\Container\Attributes\Bind;

/**
 * Finds likely personal information in a text. The rules live in llm-inspect, not in this
 * application; see docs/personal-info-detection.md.
 */
#[Bind(LlmInspectScanner::class)]
interface PersonalInfoScanner
{
    /**
     * Each finding is the id of the rule that matched and the byte range it matched in $text.
     *
     * @return list<array{rule_id: string, start: int, end: int}>
     *
     * @throws PersonalInfoDetectionUnavailable when the text could not be checked
     */
    public function scan(#[\SensitiveParameter] string $text): array;
}
