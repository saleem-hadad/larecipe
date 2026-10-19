<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType as Type;

/**
 * Turns a docs migration plan into the PHP file a reviewer reads in the pull
 * request. Page bodies are nowdoc heredocs indented with the code, so the diff
 * reads as Markdown; frontmatter is a short PHP array. The generated file is
 * valid PHP that the DocsMigration base class runs as written.
 */
final class DocsMigrationRenderer
{
    private const INDENT = 8;

    public function render(string $stub, DocsMigrationPlan $plan): string
    {
        $operations = $plan->operations === []
            ? str_repeat(' ', self::INDENT)."// Describe the change, for example:\n"
                .str_repeat(' ', self::INDENT)."// \$this->updatePage('installation', basedOn: 'sha256:...', frontmatter: ['title' => 'Installation'], body: <<<'MD'\n"
                .str_repeat(' ', self::INDENT)."//     # Installation\n"
                .str_repeat(' ', self::INDENT).'//     MD);'
            : implode("\n\n", array_map(fn (DocsMigrationOperation $operation) => $this->operation($operation), $plan->operations));

        return strtr($stub, [
            '{{ setName }}' => '"'.$plan->set.'"',
            '{{ set }}' => PhpValue::string($plan->set),
            '{{ author }}' => PhpValue::string((string) $plan->author->name),
            '{{ ref }}' => PhpValue::string((string) $plan->author->ref),
            '{{ summary }}' => PhpValue::string((string) $plan->summary),
            '{{ operations }}' => $operations,
        ]);
    }

    public function operation(DocsMigrationOperation $operation): string
    {
        $pad = str_repeat(' ', self::INDENT);
        $basedOn = $operation->basedOn !== null ? ', basedOn: '.PhpValue::string($operation->basedOn) : '';

        return $pad.match ($operation->type) {
            Type::CreatePage => '$this->createPage('.PhpValue::string($operation->target).', frontmatter: '.$this->frontmatter($operation).', body: '.$this->nowdoc((string) $operation->body).');',
            Type::UpdatePage => '$this->updatePage('.PhpValue::string($operation->target).$basedOn.', frontmatter: '.$this->frontmatter($operation).', body: '.$this->nowdoc((string) $operation->body).');',
            Type::MovePage => '$this->movePage('.PhpValue::string($operation->target).', '.PhpValue::string((string) $operation->to).$basedOn.');',
            Type::DeletePage => '$this->deletePage('.PhpValue::string($operation->target).$basedOn.');',
            Type::UpdateNavigation => '$this->updateNavigation('.ltrim(implode(', ', array_filter([
                $operation->target !== '' ? PhpValue::string($operation->target) : null,
                $operation->locale !== null ? 'locale: '.PhpValue::string($operation->locale) : null,
                $operation->basedOn !== null ? 'basedOn: '.PhpValue::string($operation->basedOn) : null,
                $operation->tree !== null ? 'tree: '.PhpValue::export($operation->tree, self::INDENT, true) : 'patch: '.PhpValue::export($operation->patch ?? [], self::INDENT, true),
            ]))).');',
            Type::UpdateManifest => '$this->updateManifest('.ltrim(implode(', ', array_filter([
                $operation->basedOn !== null ? 'basedOn: '.PhpValue::string($operation->basedOn) : null,
                'patch: '.PhpValue::export($operation->patch ?? [], self::INDENT, true),
            ]))).');',
        };
    }

    private function frontmatter(DocsMigrationOperation $operation): string
    {
        return PhpValue::export($operation->frontmatter, self::INDENT, true);
    }

    /**
     * A nowdoc indented with the code. PHP strips the closing marker's
     * indentation from every line, so the string is exactly the body; the
     * marker changes if the body has a line that would end it early.
     */
    private function nowdoc(string $body): string
    {
        $pad = str_repeat(' ', self::INDENT + 4);
        $marker = 'MD';
        $suffix = 0;

        while (preg_match('/^\s*'.preg_quote($marker, '/').'\b/m', $body)) {
            $marker = 'MD_'.++$suffix;
        }

        $lines = array_map(fn (string $line) => $line === '' ? '' : $pad.$line, explode("\n", str_replace("\r\n", "\n", $body)));

        return "<<<'{$marker}'\n".implode("\n", $lines)."\n".$pad.$marker;
    }
}
