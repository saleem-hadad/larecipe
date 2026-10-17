<?php

namespace LaRecipe\Domains\Ask\Laravel;

use Generator;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Protocols\VercelDataProtocol;
use LaRecipe\Domains\Ask\Services\CitationGuard;

/**
 * The Vercel AI SDK UI message stream (what useChat reads), plus one
 * "source-url" part per page the answer cited, sent before the stream finishes.
 *
 * Sources come only from the citation guard, so the reader UI can render them
 * as trusted links: every one is a page this reader may see.
 */
final class CitingVercelProtocol extends VercelDataProtocol
{
    public function __construct(
        ?string $messageId,
        private readonly CitationGuard $guard,
    ) {
        parent::__construct($messageId);
    }

    protected function parts(StreamableAgentResponse $response): Generator
    {
        $text = '';
        $cited = false;

        foreach (parent::parts($response) as $part) {
            if (($part['type'] ?? null) === 'text-delta') {
                $text .= (string) ($part['delta'] ?? '');
            }

            if (($part['type'] ?? null) === 'finish' && ! $cited) {
                $cited = true;
                yield from $this->sources($text);
            }

            yield $part;
        }

        if (! $cited && $this->started && ! $this->errored) {
            yield from $this->sources($text);
        }
    }

    private function sources(string $text): Generator
    {
        foreach ($this->guard->citations($text) as $citation) {
            yield [
                'type' => 'source-url',
                'sourceId' => $citation['url'],
                'url' => $citation['url'],
                'title' => $citation['title'],
            ];
        }
    }
}
