<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * The JSON an agent hook receives on stdin, reduced to what LaRecipe reads.
 *
 * Claude Code sends the Bash command as tool_input.command (a string). Other
 * agents may send an argv array; both are accepted. A payload that is not
 * JSON is treated as empty, so a malformed call never blocks anything.
 */
final readonly class HookPayload
{
    public function __construct(
        public ?string $event = null,
        public ?string $sessionId = null,
        public ?string $cwd = null,
        public ?string $toolName = null,
        public ?string $command = null,
        public ?string $source = null,
    ) {}

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        return is_array($data) ? self::fromArray($data) : new self;
    }

    public static function fromArray(array $data): self
    {
        $input = is_array($data['tool_input'] ?? null) ? $data['tool_input'] : [];
        $command = $input['command'] ?? $input['cmd'] ?? null;

        if (is_array($command)) {
            $command = implode(' ', array_map('strval', $command));
        }

        return new self(
            event: isset($data['hook_event_name']) ? (string) $data['hook_event_name'] : null,
            sessionId: isset($data['session_id']) ? (string) $data['session_id'] : null,
            cwd: isset($data['cwd']) ? (string) $data['cwd'] : null,
            toolName: isset($data['tool_name']) ? (string) $data['tool_name'] : null,
            command: is_string($command) ? $command : null,
            source: isset($data['source']) ? (string) $data['source'] : null,
        );
    }

    /** A filesystem-safe key for the loop guard's per-session state. */
    public function sessionKey(): string
    {
        $session = $this->sessionId ?? 'default';

        return preg_match('/^[A-Za-z0-9._-]{1,128}$/', $session) ? $session : substr(hash('sha256', $session), 0, 32);
    }
}
