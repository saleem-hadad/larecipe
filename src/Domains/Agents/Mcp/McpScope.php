<?php

namespace LaRecipe\Domains\Agents\Mcp;

use Illuminate\Contracts\Foundation\Application;

/**
 * Who is calling the docs tools, and which set the call is bound to.
 *
 * - local: the stdio server in the owner's repo serves every set; tools take an
 *   optional "set" argument.
 * - web: a set's HTTP endpoint is bound to that set, so a client connected to
 *   /help/mcp can never reach /handbook.
 * - assistant: Ask AI runs the same tool classes, bound to the reader's set,
 *   version and locale.
 *
 * Author tools registered with LaRecipe::mcpTool(..., author: true) should
 * resolve their set through set() too, so they honor the binding.
 *
 * Until something binds it, a call is local only when it comes from the
 * console and serves no HTTP route. Any HTTP call is a web call, so a server
 * mounted by hand (without a set's binding) gets the read-only web surface,
 * never the local one. Checking the route matters under Octane, where web
 * requests are also served from a console process.
 */
final class McpScope
{
    public const LOCAL = 'local';

    public const WEB = 'web';

    public const ASSISTANT = 'assistant';

    private ?string $transport = null;

    private ?string $set = null;

    private ?string $version = null;

    private ?string $locale = null;

    public function __construct(private readonly Application $app) {}

    public function bindWeb(string $set): void
    {
        $this->transport = self::WEB;
        $this->set = $set;
        $this->version = null;
        $this->locale = null;
    }

    public function bindAssistant(string $set, string $version, string $locale): void
    {
        $this->transport = self::ASSISTANT;
        $this->set = $set;
        $this->version = $version;
        $this->locale = $locale;
    }

    public function transport(): string
    {
        return $this->transport ?? ($this->app->runningInConsole() && ! $this->servingHttp() ? self::LOCAL : self::WEB);
    }

    public function isLocal(): bool
    {
        return $this->transport() === self::LOCAL;
    }

    public function isWeb(): bool
    {
        return $this->transport() === self::WEB;
    }

    public function isAssistant(): bool
    {
        return $this->transport() === self::ASSISTANT;
    }

    /** The set every call is bound to, or null on the local server. */
    public function boundSet(): ?string
    {
        return $this->set;
    }

    /** The set a call works on: the bound set, else the one the caller asked for (local server only). */
    public function set(?string $requested): ?string
    {
        if ($this->set !== null || ! $this->isLocal()) {
            return $this->set;
        }

        return $requested !== null && $requested !== '' ? $requested : null;
    }

    public function defaultVersion(): ?string
    {
        return $this->version;
    }

    public function defaultLocale(): ?string
    {
        return $this->locale;
    }

    private function servingHttp(): bool
    {
        return $this->app->bound('request') && $this->app->make('request')->route() !== null;
    }
}
