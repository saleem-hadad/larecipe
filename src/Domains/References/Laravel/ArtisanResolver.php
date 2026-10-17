<?php

namespace LaRecipe\Domains\References\Laravel;

use Closure;
use Illuminate\Contracts\Console\Kernel;
use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Contracts\ReferenceResolver;
use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * <Artisan command="orders:prune" />: the signature, description, arguments and
 * options from the console kernel's command registry. The command is never run.
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
        $tables = [];

        if ($definition->getArguments() !== []) {
            $tables[] = new ReferenceTable('Arguments', ['Argument', 'Description', 'Default'], array_values(array_map(
                fn (InputArgument $argument) => [
                    $argument->getName().($argument->isArray() ? '...' : ''),
                    trim($argument->getDescription().($argument->isRequired() ? ' (required)' : '')),
                    $this->value($argument->getDefault()),
                ],
                $definition->getArguments(),
            )));
        }

        if ($definition->getOptions() !== []) {
            $tables[] = new ReferenceTable('Options', ['Option', 'Description', 'Default'], array_values(array_map(
                fn (InputOption $option) => [
                    '--'.$option->getName().($option->getShortcut() ? ', -'.$option->getShortcut() : '').($option->acceptValue() ? '='.strtoupper($option->getName()) : ''),
                    $option->getDescription(),
                    $option->acceptValue() ? $this->value($option->getDefault()) : '',
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

    /** @return array<string, Command> */
    private function commands(): array
    {
        return $this->commands ??= ($this->kernel)()->all();
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
