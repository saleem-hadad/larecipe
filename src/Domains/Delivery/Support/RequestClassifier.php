<?php

namespace LaRecipe\Domains\Delivery\Support;

use Illuminate\Http\Request;

/**
 * Tells human, crawler and agent requests apart, for analytics events only.
 * Classification never changes content (the No-Cloaking Rule).
 */
final class RequestClassifier
{
    public const HUMAN = 'human';

    public const CRAWLER = 'crawler';

    public const AGENT = 'agent';

    private const AGENTS = ['Claude-User', 'Claude-Code', 'ChatGPT-User', 'Perplexity-User', 'OAI-SearchBot', 'Cursor', 'codex', 'GoogleAgent', 'MistralAI-User', 'axios', 'node-fetch', 'python-requests', 'curl', 'Go-http-client', 'undici'];

    private const CRAWLERS = ['ClaudeBot', 'GPTBot', 'PerplexityBot', 'Googlebot', 'Bingbot', 'bingbot', 'Applebot', 'CCBot', 'Bytespider', 'Amazonbot', 'DuckDuckBot', 'YandexBot', 'facebookexternalhit', 'Slackbot', 'Twitterbot', 'LinkedInBot'];

    public function classify(Request $request, ?string $format = null): string
    {
        $agent = (string) $request->userAgent();

        foreach (self::CRAWLERS as $needle) {
            if (str_contains($agent, $needle)) {
                return self::CRAWLER;
            }
        }

        foreach (self::AGENTS as $needle) {
            if (stripos($agent, $needle) !== false) {
                return self::AGENT;
            }
        }

        if ($format === 'markdown' || str_contains((string) $request->header('Accept'), 'text/markdown')) {
            return self::AGENT;
        }

        return self::HUMAN;
    }
}
