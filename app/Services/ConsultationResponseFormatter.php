<?php

namespace App\Services;

use Illuminate\Support\Str;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
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
            ->addEventListener(DocumentParsedEvent::class, $this->removeImages(...));
    }

    /**
     * External images are never loaded; their alt text is kept as plain inline content.
     */
    private function removeImages(DocumentParsedEvent $event): void
    {
        $images = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Image) {
                $images[] = $node;
            }
        }

        foreach ($images as $image) {
            foreach ($image->children() as $altTextNode) {
                $image->insertBefore($altTextNode);
            }

            $image->detach();
        }
    }
}
