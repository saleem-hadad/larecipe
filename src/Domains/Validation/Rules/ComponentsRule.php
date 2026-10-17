<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\Suggest;

/**
 * Every problem the renderer found while rendering the page, as a validation
 * issue: components.unknown (a tag it does not know, rendered as escaped text),
 * components.props (missing or invalid props), and whatever else the renderer
 * reports with a rule id (snippets.missing, media.placeholder, ...).
 *
 * Validation and rendering can never disagree about what is valid, because the
 * renderer is the one deciding.
 */
final class ComponentsRule implements PageRule
{
    public function check(PageSnapshot $page, PageContext $context): array
    {
        $id = $context->pageId($page->page->ref->path);

        if ($page->renderError !== null) {
            return [ValidationIssue::error('components.props', 'The page failed to render: '.$page->renderError, $id, file: $page->file)];
        }

        $issues = [];

        foreach ($page->rendered->issues ?? [] as $issue) {
            $message = (string) ($issue['message'] ?? '');
            $component = isset($issue['component']) ? (string) $issue['component'] : null;
            $rule = $this->rule($issue['rule'] ?? null, $message);
            $line = isset($issue['line']) ? $page->fileLine((int) $issue['line']) : ($component !== null ? $page->lineOf('<'.$component) : null);
            $level = ($issue['level'] ?? ValidationIssue::ERROR) === ValidationIssue::WARNING ? ValidationIssue::WARNING : ValidationIssue::ERROR;

            $issues[] = new ValidationIssue($level, $rule, $message, $id, $line, $this->fix($rule, $component, $context), null, $page->file);
        }

        return $issues;
    }

    /** The foundation renderer reported component problems without a rule id; later renderers send one. */
    private function rule(mixed $rule, string $message): string
    {
        if (is_string($rule) && $rule !== '' && $rule !== 'components.invalid') {
            return $rule;
        }

        return str_starts_with($message, 'Unknown component') ? 'components.unknown' : 'components.props';
    }

    private function fix(string $rule, ?string $component, PageContext $context): ?string
    {
        return match ($rule) {
            'components.unknown' => ($meant = Suggest::closest((string) $component, $context->settings->knownComponents)) !== null
                ? "Use <{$meant}>."
                : "Register it with LaRecipe::component('{$component}'), or remove the tag.",
            'components.props' => $component !== null ? "Fix the props of <{$component}>." : null,
            'snippets.missing' => 'Point the include at the file\'s current path, or mark the region with // #region name and // #endregion.',
            'media.placeholder' => 'Add src="..." when the video exists.',
            default => null,
        };
    }
}
