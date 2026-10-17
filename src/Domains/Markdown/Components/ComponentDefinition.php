<?php

namespace LaRecipe\Domains\Markdown\Components;

use Closure;

/**
 * Everything LaRecipe knows about one component tag (built-in or registered by the host).
 *
 * A component always has three faces: server HTML that works without JavaScript
 * (the fallback), portable Markdown for agents, and optionally a React island
 * that takes over in the browser.
 */
final class ComponentDefinition
{
    /**
     * A raw component keeps its content as literal text instead of parsing it as
     * Markdown (a Mermaid diagram, a TeX formula): the fallback receives it
     * escaped, the Markdown form receives it verbatim, and the island gets it as
     * the "source" prop.
     *
     * @param  array<string, array<string, mixed>>  $props  prop name => {type, default, required, enum, description}
     * @param  (Closure(array<string, mixed>, string, RenderedComponent): string)|null  $fallback  props, children HTML
     * @param  (Closure(array<string, mixed>, string, RenderedComponent): string)|null  $markdown  props, children Markdown
     */
    public function __construct(
        public readonly string $name,
        public array $props = [],
        public ?Closure $fallback = null,
        public ?Closure $markdown = null,
        public bool $island = false,
        public ?string $description = null,
        public bool $chromeOnly = false,
        public string $origin = 'builtin',
        public bool $raw = false,
    ) {}

    /**
     * Fill in defaults and coerce simple types. Unknown props pass through, so a
     * host component can accept data LaRecipe does not describe.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveProps(array $props): array
    {
        foreach ($this->props as $name => $spec) {
            if (! array_key_exists($name, $props) && array_key_exists('default', $spec)) {
                $props[$name] = $spec['default'];

                continue;
            }

            if (! array_key_exists($name, $props)) {
                continue;
            }

            $props[$name] = match ($spec['type'] ?? 'string') {
                'boolean' => is_bool($props[$name]) ? $props[$name] : ! in_array($props[$name], ['false', '0', '', 'no'], true),
                'integer' => is_numeric($props[$name]) ? (int) $props[$name] : $props[$name],
                'number' => is_numeric($props[$name]) ? (float) $props[$name] : $props[$name],
                default => $props[$name],
            };
        }

        return $props;
    }

    /** @return list<string> problems with the given props, for validation */
    public function propErrors(array $props): array
    {
        $errors = [];

        foreach ($this->props as $name => $spec) {
            if (($spec['required'] ?? false) && ! array_key_exists($name, $props)) {
                $errors[] = "<{$this->name}> needs the \"{$name}\" prop.";
            }

            if (isset($spec['enum'], $props[$name]) && ! in_array($props[$name], $spec['enum'], true)) {
                $errors[] = sprintf('<%s %s="%s"> must be one of: %s.', $this->name, $name, (string) json_encode($props[$name]), implode(', ', $spec['enum']));
            }
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'props' => $this->props,
            'island' => $this->island,
            'raw' => $this->raw,
            'origin' => $this->origin,
        ];
    }
}
