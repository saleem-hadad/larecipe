<?php

namespace LaRecipe\Domains\Ask\Services;

/**
 * The conversation a reader's browser sends with each question, made safe to
 * hand to the model.
 *
 * History lives in the browser, so anything in it may be forged. Only user and
 * assistant turns are kept, and only their text: tool results, files, tool
 * approvals and other roles never reach the model. The text is bounded, so one
 * request cannot spend the owner's provider budget on a novel.
 */
final class ChatInput
{
    /**
     * @param  array<int, mixed>  $messages  useChat UI messages, as posted
     * @return list<array{id: string, role: string, parts: list<array{type: string, text: string}>}>
     */
    public function textOnly(array $messages): array
    {
        $kept = [];

        foreach ($messages as $message) {
            if (! is_array($message) || ! in_array($message['role'] ?? null, ['user', 'assistant'], true)) {
                continue;
            }

            $parts = [];

            foreach ((array) ($message['parts'] ?? []) as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                    $parts[] = ['type' => 'text', 'text' => $part['text']];
                }
            }

            $kept[] = ['id' => is_string($message['id'] ?? null) ? $message['id'] : '', 'role' => $message['role'], 'parts' => $parts];
        }

        return $kept;
    }

    /**
     * Why the turns before the question are too long, or null when they fit:
     * each earlier question within the question limit, all earlier turns
     * together within the history limit.
     *
     * @param  list<array{id: string, role: string, parts: list<array{type: string, text: string}>}>  $messages  textOnly() output
     */
    public function tooLong(array $messages, int $questionLimit, int $historyLimit): ?string
    {
        $total = 0;

        foreach (array_slice($messages, 0, -1) as $message) {
            $length = mb_strlen(implode('', array_column($message['parts'], 'text')));

            if ($message['role'] === 'user' && $length > $questionLimit) {
                return "Ask a question of at most {$questionLimit} characters.";
            }

            $total += $length;
        }

        return $total > $historyLimit ? 'This conversation is too long for Ask AI. Start a new question.' : null;
    }
}
