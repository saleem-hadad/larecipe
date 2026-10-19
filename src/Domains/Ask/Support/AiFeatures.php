<?php

namespace LaRecipe\Domains\Ask\Support;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Streaming\Protocols\VercelDataProtocol;
use Laravel\Ai\Vercel\Vercel;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * The one place that knows whether laravel/ai is installed. It is optional:
 * the owner's own coding agent needs no provider key in the app, so Ask AI
 * (and the server-side docs agent) light up only when the host requires it.
 */
final class AiFeatures
{
    public static function available(): bool
    {
        return interface_exists(Agent::class)
            && class_exists(Vercel::class)
            && class_exists(VercelDataProtocol::class);
    }

    /** Ask AI is on for a set: laravel/ai is installed and ai.ask.enabled is true for the set. */
    public function askEnabled(DocSet $set): bool
    {
        return self::available() && (bool) $set->config->setting('ai.ask.enabled', false);
    }
}
