<?php

namespace LaRecipe\Domains\Agents\Mcp;

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
 */
final class McpScope
{
    public const LOCAL = 'local';

    public const WEB = 'web';

    public const ASSISTANT = 'assistant';

    private string $transport = self::LOCAL;

    private ?string $set = null;

    private ?string $version = null;

    private ?string $locale = null;

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
        return $this->transport;
    }

    public function isLocal(): bool
    {
        return $this->transport === self::LOCAL;
    }

    public function isWeb(): bool
    {
        return $this->transport === self::WEB;
    }

    public function isAssistant(): bool
    {
        return $this->transport === self::ASSISTANT;
    }

    /** The set every call is bound to, or null on the local server. */
    public function boundSet(): ?string
    {
        return $this->set;
    }

    /** The set a call works on: the bound set, else the one the caller asked for. */
    public function set(?string $requested): ?string
    {
        return $this->set ?? ($requested !== null && $requested !== '' ? $requested : null);
    }

    public function defaultVersion(): ?string
    {
        return $this->version;
    }

    public function defaultLocale(): ?string
    {
        return $this->locale;
    }
}
