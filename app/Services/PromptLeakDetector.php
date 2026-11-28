<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Checks AI output for content that came from the internal Zen prompts before it is shown or stored.
 * It catches configured canary markers and long verbatim runs of prompt text, both in the raw output
 * and in the text shown once the output is rendered; paraphrased or translated prompt content is not
 * detected.
 */
class PromptLeakDetector
{
    public const DETECTION_MARKER = 'marker';

    public const DETECTION_PROMPT_FRAGMENT = 'prompt_fragment';

    public const DEFAULT_FRAGMENT_LENGTH = 50;

    public const MIN_FRAGMENT_LENGTH = 20;

    public const MAX_FRAGMENT_LENGTH = 200;

    /**
     * Shorter markers would match ordinary answers, so they are ignored.
     */
    public const MIN_MARKER_LENGTH = 12;

    /**
     * Normalized prompt windows of exactly $fragmentLength characters, built on first use.
     *
     * @var array<string, true>|null
     */
    private ?array $promptFragments = null;

    /**
     * Normalized markers, built on first use.
     *
     * @var list<string>|null
     */
    private ?array $normalizedMarkers = null;

    private readonly int $fragmentLength;

    /**
     * @param  array<array-key, mixed>  $prompts  The internal prompts, nested by name and locale.
     * @param  list<string>  $markers  Canary strings that must never appear in output.
     * @param  int  $fragmentLength  Minimum normalized length of a verbatim prompt run treated as a leak;
     *                               values outside the allowed range fall back to the default.
     */
    public function __construct(
        #[\SensitiveParameter] private readonly array $prompts,
        #[\SensitiveParameter] private readonly array $markers,
        int $fragmentLength = self::DEFAULT_FRAGMENT_LENGTH,
        private readonly PromptLeakTextNormalizer $normalizer = new PromptLeakTextNormalizer,
    ) {
        $this->fragmentLength = $fragmentLength >= self::MIN_FRAGMENT_LENGTH && $fragmentLength <= self::MAX_FRAGMENT_LENGTH
            ? $fragmentLength
            : self::DEFAULT_FRAGMENT_LENGTH;
    }

    /**
     * Builds the detector from config, replacing invalid settings with safe values. Warnings name the
     * setting only, never its value, because markers are secret.
     */
    public static function fromConfig(): self
    {
        $normalizer = new PromptLeakTextNormalizer;

        $configuredLength = config('zen.prompt_leak.fragment_length');
        $fragmentLength = self::DEFAULT_FRAGMENT_LENGTH;
        if ($configuredLength !== null && $configuredLength !== '') {
            $validatedLength = filter_var($configuredLength, FILTER_VALIDATE_INT, ['options' => [
                'min_range' => self::MIN_FRAGMENT_LENGTH,
                'max_range' => self::MAX_FRAGMENT_LENGTH,
            ]]);
            if ($validatedLength === false) {
                Log::warning('Invalid prompt leak fragment length; using the default.', [
                    'setting' => 'ZEN_PROMPT_LEAK_FRAGMENT_LENGTH',
                    'default' => self::DEFAULT_FRAGMENT_LENGTH,
                ]);
            } else {
                $fragmentLength = $validatedLength;
            }
        }

        $configuredMarkers = config('zen.prompt_leak.markers', []);
        $markers = [];
        $ignoredMarkers = 0;
        foreach (is_array($configuredMarkers) ? $configuredMarkers : [] as $marker) {
            if (is_string($marker) && mb_strlen($normalizer->normalize($marker)) >= self::MIN_MARKER_LENGTH) {
                $markers[] = $marker;
            } else {
                $ignoredMarkers++;
            }
        }
        if ($ignoredMarkers > 0 || ! is_array($configuredMarkers)) {
            Log::warning('Ignored invalid prompt leak markers.', [
                'setting' => 'ZEN_PROMPT_LEAK_MARKERS',
                'ignored' => $ignoredMarkers,
                'minimum_length' => self::MIN_MARKER_LENGTH,
            ]);
        }

        return new self((array) config('zen.prompts', []), $markers, $fragmentLength, $normalizer);
    }

    /**
     * Returns the detection type when any output leaks prompt content, or null when all are clean.
     */
    public function detect(#[\SensitiveParameter] ?string ...$outputs): ?string
    {
        foreach ($outputs as $output) {
            if ($output === null || $output === '') {
                continue;
            }

            foreach ($this->normalizer->variants($output) as $normalizedOutput) {
                foreach ($this->normalizedMarkers() as $normalizedMarker) {
                    if (str_contains($normalizedOutput, $normalizedMarker)) {
                        return self::DETECTION_MARKER;
                    }
                }

                if ($this->containsPromptFragment($normalizedOutput)) {
                    return self::DETECTION_PROMPT_FRAGMENT;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function normalizedMarkers(): array
    {
        return $this->normalizedMarkers ??= array_values(array_filter(
            array_map($this->normalizer->normalize(...), $this->markers),
            fn (string $marker): bool => $marker !== '',
        ));
    }

    private function containsPromptFragment(string $normalizedOutput): bool
    {
        $fragments = $this->promptFragments();
        $characters = mb_str_split($normalizedOutput);
        $lastStart = count($characters) - $this->fragmentLength;

        for ($start = 0; $start <= $lastStart; $start++) {
            if (isset($fragments[implode('', array_slice($characters, $start, $this->fragmentLength))])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function promptFragments(): array
    {
        if ($this->promptFragments !== null) {
            return $this->promptFragments;
        }

        $fragments = [];
        $prompts = $this->prompts;
        array_walk_recursive($prompts, function (mixed $prompt) use (&$fragments): void {
            if (! is_string($prompt)) {
                return;
            }

            foreach ($this->normalizer->variants($prompt) as $normalizedPrompt) {
                $characters = mb_str_split($normalizedPrompt);
                $lastStart = count($characters) - $this->fragmentLength;
                for ($start = 0; $start <= $lastStart; $start++) {
                    $fragments[implode('', array_slice($characters, $start, $this->fragmentLength))] = true;
                }
            }
        });

        return $this->promptFragments = $fragments;
    }
}
