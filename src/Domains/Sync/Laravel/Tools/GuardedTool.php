<?php

namespace LaRecipe\Domains\Sync\Laravel\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use LaRecipe\Domains\Sync\Services\SyncLedger;

/**
 * Wraps one of the shared MCP tools (local, or a remote set's) for a sync run.
 *
 * The tool itself is unchanged, so the agent calls exactly the code every
 * other agent calls. The wrapper only applies the run's decisions: the set is
 * fixed (the agent cannot reach another set), the run's version and locale
 * are filled in when the agent leaves them out, and every write goes through
 * the ledger, which enforces the page budget and the dry run and records the
 * outcome for the report.
 */
final class GuardedTool implements Tool
{
    /** @var list<string>|null the argument names the wrapped tool declares */
    private ?array $declared = null;

    /**
     * @param  array<string, mixed>  $forced  arguments the run decides, hidden from the agent
     * @param  array<string, mixed>  $defaults  arguments used when the agent leaves them out
     * @param  list<string>  $hidden  arguments never sent (a remote endpoint is bound to its own set)
     */
    public function __construct(
        private readonly Tool $inner,
        private readonly string $toolName,
        private readonly SyncLedger $ledger,
        private readonly array $forced = [],
        private readonly array $defaults = [],
        private readonly array $hidden = [],
    ) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return (string) $this->inner->description();
    }

    public function schema(JsonSchema $schema): array
    {
        $properties = $this->inner->schema($schema);

        foreach ([...array_keys($this->forced), ...$this->hidden] as $name) {
            unset($properties[$name]);
        }

        return $properties;
    }

    public function handle(Request $request): string
    {
        $arguments = $this->arguments($request->all());

        if (SyncLedger::isWrite($this->toolName) && ($refusal = $this->ledger->admit($this->toolName, $arguments)) !== null) {
            return $refusal;
        }

        $result = (string) $this->inner->handle(new Request($arguments, $request->toolCallId(), $request->toolInvocationId()));

        if (SyncLedger::isWrite($this->toolName)) {
            $this->ledger->record($this->toolName, $arguments, $result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function arguments(array $arguments): array
    {
        foreach ($this->hidden as $name) {
            unset($arguments[$name]);
        }

        foreach ($this->defaults as $name => $value) {
            if (in_array($name, $this->declared(), true) && ($arguments[$name] ?? null) === null) {
                $arguments[$name] = $value;
            }
        }

        foreach ($this->forced as $name => $value) {
            if (in_array($name, $this->declared(), true)) {
                $arguments[$name] = $value;
            }
        }

        return $arguments;
    }

    /** @return list<string> */
    private function declared(): array
    {
        return $this->declared ??= array_map('strval', array_keys($this->inner->schema(new JsonSchemaTypeFactory)));
    }
}
