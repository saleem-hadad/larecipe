<?php

namespace LaRecipe\Domains\Sync\Services;

/**
 * Finds the things a page names that live in the codebase: fully qualified
 * class names, artisan commands and route names, from inline code, shell
 * blocks and live reference components.
 *
 * It only proposes candidates; the Codebase contract decides which of them
 * are defined in this project.
 */
final class CodeMentions
{
    /** @return list<array{kind: string, name: string, evidence: string}> */
    public function find(string $markdown): array
    {
        $mentions = [];

        foreach ($this->inlineCode($markdown) as $code) {
            array_push($mentions, ...$this->classify($code));
        }

        // Commands in shell code blocks: "php artisan orders:prune --days=30".
        if (preg_match_all('/^\s*(?:\$\s*)?(?:php\s+)?artisan\s+([a-z][a-z0-9-]*(?::[a-z0-9-]+)+)/mi', $markdown, $matches)) {
            foreach ($matches[1] as $index => $command) {
                $mentions[] = ['kind' => 'command', 'name' => $command, 'evidence' => trim($matches[0][$index])];
            }
        }

        // Live reference components already name their target.
        $components = [
            'Route' => ['name', 'route'],
            'Artisan' => ['command', 'command'],
            'Model' => ['name', 'model'],
            'Event' => ['name', 'event'],
        ];

        foreach ($components as $tag => [$prop, $kind]) {
            if (preg_match_all('/<'.$tag.'\b[^>]*\b'.$prop.'="([^"]+)"/', $markdown, $matches)) {
                foreach ($matches[1] as $index => $name) {
                    $mentions[] = ['kind' => $kind, 'name' => $name, 'evidence' => $matches[0][$index].' />'];
                }
            }
        }

        $unique = [];

        foreach ($mentions as $mention) {
            $unique[$mention['kind'].':'.$mention['name']] ??= $mention;
        }

        return array_values($unique);
    }

    /** @return list<string> */
    private function inlineCode(string $markdown): array
    {
        $withoutFences = preg_replace('/^(```|~~~).*?^\1/ms', '', $markdown) ?? $markdown;

        preg_match_all('/(?<!`)`([^`\n]+)`(?!`)/', $withoutFences, $matches);

        return array_values(array_unique(array_map('trim', $matches[1])));
    }

    /** @return list<array{kind: string, name: string, evidence: string}> */
    private function classify(string $code): array
    {
        $evidence = '`'.$code.'`';

        if (preg_match('/^\\\\?([A-Z][A-Za-z0-9_]*(?:\\\\[A-Z][A-Za-z0-9_]*)+)(?:::[A-Za-z_][A-Za-z0-9_]*(?:\(\))?)?$/', $code, $match)) {
            return [['kind' => 'class', 'name' => $match[1], 'evidence' => $evidence]];
        }

        if (preg_match('/^(?:php\s+)?artisan\s+([a-z][a-z0-9-]*(?::[a-z0-9-]+)+)/i', $code, $match)) {
            return [['kind' => 'command', 'name' => $match[1], 'evidence' => $evidence]];
        }

        if (preg_match('/route\(\s*[\'"]([^\'"]+)[\'"]/', $code, $match)) {
            return [['kind' => 'route', 'name' => $match[1], 'evidence' => $evidence]];
        }

        if (preg_match('/^[a-z][a-z0-9-]*(?::[a-z0-9-]+)+$/', $code)) {
            return [['kind' => 'command', 'name' => $code, 'evidence' => $evidence]];
        }

        // "orders.store" could be a route name or a file name; the codebase decides.
        if (preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-z0-9_-]+)+$/', $code) && ! preg_match('/\.(php|js|ts|json|md|yml|yaml|env|css|html|txt|xml|lock)$/', $code)) {
            return [['kind' => 'route', 'name' => $code, 'evidence' => $evidence]];
        }

        return [];
    }
}
