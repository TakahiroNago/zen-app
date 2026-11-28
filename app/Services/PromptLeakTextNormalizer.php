<?php

namespace App\Services;

use Dom\HTMLDocument;
use Normalizer;

/**
 * Produces the comparison forms of a text for prompt leak checks: the raw text as the model wrote
 * it, and the text a reader actually sees once it is rendered as Markdown like a consultation answer.
 * Both are folded so that formatting, escapes, entities, invisible characters, width, case and
 * whitespace cannot split a leaked string.
 */
class PromptLeakTextNormalizer
{
    public function __construct(
        private readonly ConsultationResponseFormatter $formatter = new ConsultationResponseFormatter,
    ) {}

    /**
     * Returns the distinct normalized forms of the text: raw and as rendered for display.
     *
     * @return list<string>
     */
    public function variants(#[\SensitiveParameter] string $text): array
    {
        return array_values(array_unique(array_filter([
            $this->normalize($text),
            $this->normalize($this->visibleText($text)),
        ], fn (string $variant): bool => $variant !== '')));
    }

    /**
     * Folds width (NFKC), case, whitespace including special spaces, and invisible format characters
     * such as zero-width spaces and soft hyphens.
     */
    public function normalize(#[\SensitiveParameter] string $text): string
    {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);
        $normalized = is_string($normalized) ? $normalized : $text;

        return mb_strtolower((string) preg_replace('/[\s\p{Z}\p{Cf}]+/u', '', $normalized));
    }

    /**
     * The text content of the text rendered with the same Markdown formatter as consultation answers,
     * extracted by an HTML5 parser so tags are dropped and entities are decoded.
     */
    private function visibleText(#[\SensitiveParameter] string $text): string
    {
        $html = $this->formatter->toHtml($text);
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
            LIBXML_NOERROR,
        );

        return (string) $document->body?->textContent;
    }
}
