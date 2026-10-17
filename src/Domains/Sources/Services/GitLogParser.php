<?php

namespace LaRecipe\Domains\Sources\Services;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;

/**
 * Reads `git log` output into the last change of each file: who committed it,
 * which agent wrote it (a Co-Authored-By trailer naming Claude, Codex, Copilot
 * and friends, or an agent's bot account), and the pull request it came in,
 * from the "(#412)" a squash merge adds or "Merge pull request #412".
 *
 * Expects the format GitProvenanceResolver asks for: records separated by
 * \x1e, header fields by \x1f, and the message closed by \x1d before the
 * changed file names.
 */
final class GitLogParser
{
    public const FORMAT = '%x1e%H%x1f%an%x1f%ae%x1f%cI%x1f%B%x1d';

    /** Agents recognized in trailers and author names, with the name readers see. */
    private const AGENTS = [
        '/\bclaude\b|anthropic/i' => 'Claude',
        '/\bcodex\b|openai/i' => 'Codex',
        '/\bcopilot\b/i' => 'Copilot',
        '/\bcursor(-agent)?\b/i' => 'Cursor',
        '/\bgemini\b|\bjules\b/i' => 'Gemini',
        '/\bdevin\b/i' => 'Devin',
        '/\baider\b/i' => 'Aider',
        '/\bwindsurf\b|codeium/i' => 'Windsurf',
    ];

    /**
     * @return array<string, Provenance> the newest change per file
     */
    public function parse(string $log, ?string $repositoryUrl = null): array
    {
        $files = [];

        foreach (explode("\x1e", $log) as $record) {
            if (trim($record) === '' || ! str_contains($record, "\x1d")) {
                continue;
            }

            [$header, $names] = explode("\x1d", $record, 2);
            $fields = explode("\x1f", $header, 5);

            if (count($fields) < 5) {
                continue;
            }

            [$sha, $author, $email, $date, $message] = $fields;
            $provenance = null;

            foreach (preg_split('/\R/', $names) ?: [] as $name) {
                $name = trim($name);

                if ($name === '' || isset($files[$name])) {
                    continue;
                }

                $files[$name] = $provenance ??= $this->provenance($sha, $author, $email, $date, $message, $repositoryUrl);
            }
        }

        return $files;
    }

    private function provenance(string $sha, string $author, string $email, string $date, string $message, ?string $repositoryUrl): Provenance
    {
        $at = new DateTimeImmutable($date);
        $ref = $this->reference($sha, $message, $repositoryUrl);
        $agent = $this->agentFromTrailers($message);

        if ($agent !== null) {
            return new Provenance(Actor::Agent, $agent, $ref, $at, $this->agentName($author.' '.$email) === null ? $author : null);
        }

        $bot = $this->agentName($author.' '.$email);

        return $bot !== null && str_contains(strtolower($author.$email), 'bot')
            ? new Provenance(Actor::Agent, $bot, $ref, $at)
            : new Provenance(Actor::Human, $author, $ref, $at);
    }

    private function agentFromTrailers(string $message): ?string
    {
        if (! preg_match_all('/^co-authored-by:\s*(.+?)\s*(?:<([^>]*)>)?\s*$/mi', $message, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            if ($this->agentName($match[1].' '.($match[2] ?? '')) !== null) {
                $name = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $match[1]) ?? $match[1]);

                return $name !== '' ? $name : $this->agentName($match[1]);
            }
        }

        return null;
    }

    private function agentName(string $text): ?string
    {
        foreach (self::AGENTS as $pattern => $name) {
            if (preg_match($pattern, $text)) {
                return $name;
            }
        }

        return null;
    }

    /** The pull request when the message names one, otherwise the commit. */
    private function reference(string $sha, string $message, ?string $repositoryUrl): string
    {
        $subject = strtok($message, "\n") ?: '';

        if (preg_match('/\(#(\d+)\)\s*$/', $subject, $match) || preg_match('/pull request #(\d+)/i', $subject, $match)) {
            return $repositoryUrl !== null ? rtrim($repositoryUrl, '/').'/pull/'.$match[1] : '#'.$match[1];
        }

        return substr($sha, 0, 12);
    }
}
