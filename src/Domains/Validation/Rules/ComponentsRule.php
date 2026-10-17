<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\Suggest;

/**
 * components.unknown: a component tag the renderer does not know (it renders
 * as escaped text, so readers would see the raw tag).
 * components.props: a known component with missing or invalid props.
 *
 * Both come straight from the renderer, so validation and rendering can never
 * disagree about what a valid component is.
 */
final class ComponentsRule implements PageRule
{
    public function check(PageSnapshot $page, PageContext $context): array
    {
        $id = $context->pageId($page->page->ref->path);
        $issues = [];

        if ($page->renderError !== null) {
            return [ValidationIssue::error('components.props', 'The page failed to render: '.$page->renderError, $id, file: $page->file)];
        }

        foreach ($page->rendered->issues ?? [] as $issue) {
            $name = (string) $issue['component'];
            $line = $page->lineOf('<'.$name) ?? $page->lineOf(':::'.strtolower($name));

            if (str_starts_with((string) $issue['message'], 'Unknown component')) {
                $meant = Suggest::closest($name, $context->settings->knownComponents);
                $fix = $meant !== null
                    ? "Use <{$meant}>."
                    : "Register it with LaRecipe::component('{$name}'), or remove the tag.";

                $issues[] = ValidationIssue::error('components.unknown', $issue['message'], $id, $line, $fix, $page->file);

                continue;
            }

            $issues[] = ValidationIssue::error('components.props', (string) $issue['message'], $id, $line, 'Fix the props of <'.$name.'>.', $page->file);
        }

        return $issues;
    }
}
