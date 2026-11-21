<?php

namespace App\Services;

use Illuminate\Support\Str;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;

class ConsultationResponseFormatter implements ExtensionInterface
{
    public function toHtml(#[\SensitiveParameter] string $response): string
    {
        return (string) Str::markdown($response, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [$this]);
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addInlineParser(new CjkFriendlyEmphasisParser, 100)
            ->addEventListener(DocumentParsedEvent::class, $this->removeLinksAndImages(...));
    }

    /**
     * Responses never link out or load external images, so a crafted concern cannot turn a
     * (publicly shareable) response into a clickable lure. The link text and the image alt text
     * are kept as plain inline content; destinations and titles are dropped.
     */
    private function removeLinksAndImages(DocumentParsedEvent $event): void
    {
        $webResources = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Link || $node instanceof Image) {
                $webResources[] = $node;
            }
        }

        foreach ($webResources as $webResource) {
            foreach ($webResource->children() as $labelNode) {
                $webResource->insertBefore($labelNode);
            }

            $webResource->detach();
        }
    }
}
