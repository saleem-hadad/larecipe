<?php

namespace LaRecipe\Domains\Onboarding\Services;

/**
 * Turns an agent's streamed output into short progress lines as it arrives.
 *
 * Claude Code's stream-json events become "Reading app/Http/...", "Writing
 * docs/api.md" and the assistant's own words; anything that is not JSON
 * (Codex's progress, a plain-text agent) passes through unchanged.
 */
final class AgentOutput
{
    private string $buffer = '';

    /** @return list<string> complete lines ready to print */
    public function feed(string $chunk): array
    {
        $this->buffer .= $chunk;
        $lines = [];

        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $newline);
            $this->buffer = substr($this->buffer, $newline + 1);
            array_push($lines, ...$this->render($line));
        }

        return $lines;
    }

    /** @return list<string> */
    public function flush(): array
    {
        $rest = $this->buffer;
        $this->buffer = '';

        return trim($rest) === '' ? [] : $this->render($rest);
    }

    /** @return list<string> */
    private function render(string $line): array
    {
        $event = json_decode($line, true);

        if (! is_array($event) || ! isset($event['type'])) {
            return trim($line) === '' ? [] : [rtrim($line)];
        }

        return match ($event['type']) {
            'assistant' => $this->assistant((array) ($event['message']['content'] ?? [])),
            'result' => isset($event['result']) && is_string($event['result']) ? ['', trim($event['result'])] : [],
            default => [],
        };
    }

    /** @return list<string> */
    private function assistant(array $content): array
    {
        $lines = [];

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            if (($part['type'] ?? null) === 'text' && trim((string) ($part['text'] ?? '')) !== '') {
                $lines[] = trim((string) $part['text']);
            }

            if (($part['type'] ?? null) === 'tool_use') {
                $lines[] = '  > '.$this->tool((string) ($part['name'] ?? 'tool'), (array) ($part['input'] ?? []));
            }
        }

        return $lines;
    }

    private function tool(string $name, array $input): string
    {
        $target = $input['file_path'] ?? $input['path'] ?? $input['pattern'] ?? $input['command'] ?? null;

        return trim($name.' '.(is_string($target) ? $target : ''));
    }
}
