<?php

namespace LaRecipe\Domains\References\Laravel;

use Closure;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Console\Parser;
use Illuminate\Contracts\Console\Kernel;
use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Contracts\ReferenceResolver;
use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * <Artisan command="orders:prune" />: the signature, description, arguments and
 * options from the console kernel's command registry. The command is never run.
 *
 * Hidden commands are never described. A default is shown only when it is
 * written in the command's $signature as declared in its class: a command
 * that builds its definition at runtime (getOptions(), a closure command, a
 * signature rebuilt in the constructor) could fill a default from config, so
 * its defaults are left out.
 *
 * The kernel is asked for lazily, so web requests that render no command
 * reference never load the console.
 */
final class ArtisanResolver implements ListsReferenceTargets, ReferenceResolver
{
    /** @var array<string, Command>|null */
    private ?array $commands = null;

    /** @param Closure(): Kernel $kernel */
    public function __construct(private readonly Closure $kernel) {}

    public function kind(): string
    {
        return 'artisan';
    }

    public function resolve(string $target, array $props = []): ?Reference
    {
        $command = $this->commands()[$target] ?? null;

        if ($command === null) {
            return null;
        }

        $definition = $command->getDefinition();
        $literal = $this->literalDefaults($command);
        $tables = [];

        if ($definition->getArguments() !== []) {
            $tables[] = new ReferenceTable('Arguments', ['Argument', 'Description', 'Default'], array_values(array_map(
                fn (InputArgument $argument) => [
                    $argument->getName().($argument->isArray() ? '...' : ''),
                    trim($argument->getDescription().($argument->isRequired() ? ' (required)' : '')),
                    $this->default($literal['arguments'][$argument->getName()] ?? null, $argument->getDefault()),
                ],
                $definition->getArguments(),
            )));
        }

        if ($definition->getOptions() !== []) {
            $tables[] = new ReferenceTable('Options', ['Option', 'Description', 'Default'], array_values(array_map(
                fn (InputOption $option) => [
                    '--'.$option->getName().($option->getShortcut() ? ', -'.$option->getShortcut() : '').($option->acceptValue() ? '='.strtoupper($option->getName()) : ''),
                    $option->getDescription(),
                    $option->acceptValue() ? $this->default($literal['options'][$option->getName()] ?? null, $option->getDefault()) : '',
                ],
                $definition->getOptions(),
            )));
        }

        return new Reference(
            kind: 'artisan',
            target: $target,
            title: (string) $command->getName(),
            description: $command->getDescription() !== '' ? $command->getDescription() : null,
            usage: 'php artisan '.$command->getSynopsis(),
            tables: $tables,
        );
    }

    public function targets(): array
    {
        $names = array_keys($this->commands());
        sort($names);

        return array_values(array_map('strval', $names));
    }

    public function fingerprint(): string
    {
        $shapes = [];

        foreach ($this->commands() as $name => $command) {
            $shapes[] = $name.' '.$command->getSynopsis().' '.$command->getDescription();
        }

        sort($shapes);

        return hash('xxh128', implode("\n", $shapes));
    }

    /** @return array<string, Command> every command the app lists (hidden ones left out) */
    private function commands(): array
    {
        return $this->commands ??= array_filter(($this->kernel)()->all(), fn (Command $command) => ! $command->isHidden());
    }

    /**
     * The defaults written in the class's declared $signature, by input name.
     * Empty when there is no such signature: then no default can be trusted.
     *
     * @return array{arguments: array<string, mixed>, options: array<string, mixed>}
     */
    private function literalDefaults(Command $command): array
    {
        $declared = $command instanceof LaravelCommand ? ((new ReflectionClass($command))->getDefaultProperties()['signature'] ?? null) : null;
        $defaults = ['arguments' => [], 'options' => []];

        if (! is_string($declared) || trim($declared) === '') {
            return $defaults;
        }

        try {
            [, $arguments, $options] = Parser::parse($declared);
        } catch (Throwable) {
            return $defaults;
        }

        foreach ($arguments as $argument) {
            $defaults['arguments'][$argument->getName()] = $argument->getDefault();
        }

        foreach ($options as $option) {
            $defaults['options'][$option->getName()] = $option->getDefault();
        }

        return $defaults;
    }

    /** The default, when the running command still has the one its signature declares. */
    private function default(mixed $declared, mixed $actual): string
    {
        return $declared !== null && $declared === $actual ? $this->value($actual) : '';
    }

    private function value(mixed $default): string
    {
        return match (true) {
            $default === null, $default === false, $default === [] => '',
            $default === true => 'true',
            is_scalar($default) => (string) $default,
            is_array($default) => implode(', ', array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $default)),
            default => '',
        };
    }
}
