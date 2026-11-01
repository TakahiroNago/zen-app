<?php

namespace App\Services;

use League\CommonMark\Delimiter\Delimiter;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Util\RegexHelper;

/**
 * Parses `*` emphasis delimiters like CommonMark, except that Japanese / fullwidth punctuation
 * such as 「」、。 counts as an ordinary character when deciding whether a run can open or close.
 * Without this, `**「…」**を` is not closed because 」 is punctuation followed by a letter.
 * The delimiter is pushed onto the regular delimiter stack, so matching, nesting and escaping stay
 * entirely in the CommonMark parser.
 */
class CjkFriendlyEmphasisParser implements InlineParserInterface
{
    private const CJK_PUNCTUATION = '/^[\x{3001}-\x{303F}\x{30FB}\x{FF01}-\x{FF60}]$/u';

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('*');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $charBefore = $cursor->peek(-1) ?? "\n";

        $numDelims = 0;
        while ($cursor->peek($numDelims) === '*') {
            $numDelims++;
        }

        $cursor->advanceBy($numDelims);
        $charAfter = $cursor->getCurrentCharacter() ?? "\n";

        [$canOpen, $canClose] = $this->determineCanOpenOrClose($charBefore, $charAfter);
        $delimiterText = str_repeat('*', $numDelims);

        if (! $canOpen && ! $canClose) {
            $inlineContext->getContainer()->appendChild(new Text($delimiterText));

            return true;
        }

        $node = new Text($delimiterText, ['delim' => true]);
        $inlineContext->getContainer()->appendChild($node);
        $inlineContext->getDelimiterStack()->push(
            new Delimiter('*', $numDelims, $node, $canOpen, $canClose, $cursor->getPosition()),
        );

        return true;
    }

    /**
     * @return array{bool, bool}
     */
    private function determineCanOpenOrClose(string $charBefore, string $charAfter): array
    {
        $afterIsWhitespace = preg_match(RegexHelper::REGEX_UNICODE_WHITESPACE_CHAR, $charAfter) === 1;
        $beforeIsWhitespace = preg_match(RegexHelper::REGEX_UNICODE_WHITESPACE_CHAR, $charBefore) === 1;
        $afterIsPunctuation = $this->isPunctuation($charAfter);
        $beforeIsPunctuation = $this->isPunctuation($charBefore);

        $canOpen = ! $afterIsWhitespace && (! $afterIsPunctuation || $beforeIsWhitespace || $beforeIsPunctuation);
        $canClose = ! $beforeIsWhitespace && (! $beforeIsPunctuation || $afterIsWhitespace || $afterIsPunctuation);

        return [$canOpen, $canClose];
    }

    private function isPunctuation(string $character): bool
    {
        return preg_match(RegexHelper::REGEX_PUNCTUATION, $character) === 1
            && preg_match(self::CJK_PUNCTUATION, $character) !== 1;
    }
}
