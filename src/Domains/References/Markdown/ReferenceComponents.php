<?php

namespace LaRecipe\Domains\References\Markdown;

use Closure;
use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\Services\ReferenceHtml;
use LaRecipe\Domains\References\Services\ReferenceMarkdown;

/**
 * The live reference components: <Route name>, <Artisan command>, <Model name>
 * and <Event name>.
 *
 * They are ordinary component nodes, so every renderer that knows components
 * (the article HTML, agent Markdown, search records) handles them with no
 * special case. ResolveReferences resolves each one once per render and leaves
 * the result in the node's props under RESOLVED; the server fallback and the
 * Markdown form below render from it. There is no <Config> and no <Env>.
 */
final class ReferenceComponents
{
    /** The prop the resolved reference travels in (an array, or null when it did not resolve). */
    public const RESOLVED = '__reference';

    /** component name => [kind, the prop that names the target] */
    public const COMPONENTS = [
        'Route' => ['route', 'name'],
        'Artisan' => ['artisan', 'command'],
        'Model' => ['model', 'name'],
        'Event' => ['event', 'name'],
    ];

    public static function kindOf(string $component): ?string
    {
        return self::COMPONENTS[$component][0] ?? null;
    }

    /** @param Closure(): ResolveReferenceAction $resolve hands out the current resolver, so a long-lived definition never holds a stale one */
    public static function register(ComponentRegistry $registry, Closure $resolve): void
    {
        $descriptions = [
            'Route' => ['A named route: method, URI, middleware and the form request rules, read from the app.', 'The route name, e.g. orders.store'],
            'Artisan' => ['An artisan command: signature, description, arguments and options, read from the app.', 'The command name, e.g. orders:prune'],
            'Model' => ['An Eloquent model: table, columns, casts, fillable and relations, read from the app.', 'The model class, e.g. Order or App\\Models\\Order'],
            'Event' => ['An event: its payload and its registered listeners, read from the app.', 'The event class, e.g. OrderShipped or App\\Events\\OrderShipped'],
        ];

        foreach (self::COMPONENTS as $name => [$kind, $prop]) {
            $registry->register(new ComponentDefinition(
                name: $name,
                props: [$prop => ['type' => 'string', 'required' => true, 'description' => $descriptions[$name][1]]],
                fallback: function (array $props) use ($kind, $prop, $resolve) {
                    $reference = self::reference($props, $kind, $prop, $resolve);

                    return $reference === null
                        ? (new ReferenceHtml)->missing($kind, (string) ($props[$prop] ?? ''))
                        : (new ReferenceHtml)->render($reference);
                },
                markdown: function (array $props) use ($kind, $prop, $resolve) {
                    $reference = self::reference($props, $kind, $prop, $resolve);

                    return $reference === null
                        ? (new ReferenceMarkdown)->missing($kind, (string) ($props[$prop] ?? ''))
                        : (new ReferenceMarkdown)->render($reference);
                },
                description: $descriptions[$name][0],
            ));
        }
    }

    /** The reference ResolveReferences left on the node, or a fresh resolution when it did not run. */
    private static function reference(array $props, string $kind, string $prop, Closure $resolve): ?Reference
    {
        if (array_key_exists(self::RESOLVED, $props)) {
            return is_array($props[self::RESOLVED]) ? Reference::fromArray($props[self::RESOLVED]) : null;
        }

        return $resolve()->execute($kind, (string) ($props[$prop] ?? ''), $props);
    }
}
