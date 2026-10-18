<?php

namespace LaRecipe\Domains\Validation\Services;

use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\Rules\CacheStoreRule;
use LaRecipe\Domains\Validation\Rules\ComponentsRule;
use LaRecipe\Domains\Validation\Rules\DuplicatePagesRule;
use LaRecipe\Domains\Validation\Rules\FrontmatterSchemaRule;
use LaRecipe\Domains\Validation\Rules\LinksRule;
use LaRecipe\Domains\Validation\Rules\ManifestSchemaRule;
use LaRecipe\Domains\Validation\Rules\MarkdownSizeRule;
use LaRecipe\Domains\Validation\Rules\MediaPlaceholderRule;
use LaRecipe\Domains\Validation\Rules\NavigationRule;
use LaRecipe\Domains\Validation\Rules\PageBasicsRule;
use LaRecipe\Domains\Validation\Rules\RedirectsRule;
use LaRecipe\Domains\Validation\Rules\SearchBudgetRule;
use LaRecipe\Domains\Validation\Rules\SkillsSpecRule;
use LaRecipe\Domains\Validation\Rules\SnippetsRule;
use LaRecipe\Domains\Validation\Rules\StylesheetsRule;

/**
 * Every validation rule, built-in ones first. Other domains add theirs with
 * add() (live references add references.missing, for example), so
 * larecipe:validate, validate_docs and the pre-write check all run the same list.
 */
final class RuleRegistry
{
    /** @var list<PageRule> */
    private array $pageRules;

    /** @var list<SetRule> */
    private array $setRules;

    public function __construct(SchemaValidator $schemas, MarkdownScanner $scanner, LinkPaths $paths, FrontmatterParser $parser)
    {
        $this->pageRules = [
            new PageBasicsRule,
            new FrontmatterSchemaRule($schemas),
            new ComponentsRule,
            new MediaPlaceholderRule($scanner),
            new SnippetsRule($scanner),
            new LinksRule($paths),
            new MarkdownSizeRule,
        ];

        $this->setRules = [
            new ManifestSchemaRule($schemas),
            new NavigationRule($schemas),
            new DuplicatePagesRule,
            new RedirectsRule,
            new SearchBudgetRule,
            new SkillsSpecRule($parser),
            new CacheStoreRule,
            new StylesheetsRule,
        ];
    }

    public function add(PageRule|SetRule $rule): void
    {
        if ($rule instanceof PageRule) {
            $this->pageRules[] = $rule;
        }

        if ($rule instanceof SetRule) {
            $this->setRules[] = $rule;
        }
    }

    /** @return list<PageRule> */
    public function pageRules(): array
    {
        return $this->pageRules;
    }

    /** @return list<SetRule> */
    public function setRules(): array
    {
        return $this->setRules;
    }
}
