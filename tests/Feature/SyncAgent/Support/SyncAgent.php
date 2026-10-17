<?php

namespace LaRecipe\Tests\Feature\SyncAgent\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Responses\Data\ToolCall;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;
use LaRecipe\Tests\TestCase;

/**
 * Helpers for the larecipe:sync tests: the track-e fixture app in a temporary
 * git repository, a fake provider key, tool calls for DocsMaintainer::fake(),
 * and a recorder of every tool result the fake model received.
 */
final class SyncAgent
{
    /** A key-shaped value that must never reach the model. */
    public const PROVIDER_KEY = 'sk-test-provider-key-never-sent';

    private static int $calls = 0;

    /**
     * The fixture app, committed on main, checked out on a "feature" branch
     * (unless $git is false), mounted as the test app's base path.
     *
     * @param  array<string, array<string, mixed>>|null  $sets
     * @param  array<string, mixed>  $config
     */
    public static function project(Application $app, bool $git = true, ?array $sets = null, array $config = []): TemporaryProject
    {
        $project = TemporaryProject::create();
        $project->copy(TestCase::fixture('track-e/app'));

        if ($git) {
            $project->init();
            $project->commit('Initial app and docs');
            $project->branch('feature');
        }

        return $project->mount($app, $sets, [...self::providerConfig(), ...$config]);
    }

    /** @return array<string, mixed> */
    public static function providerConfig(): array
    {
        return [
            'ai.default' => 'openai',
            'ai.providers.openai.key' => self::PROVIDER_KEY,
            'larecipe.ai.sync.provider' => null,
            'larecipe.ai.sync.model' => 'gpt-test',
        ];
    }

    /** @param array<string, mixed> $arguments */
    public static function call(string $tool, array $arguments = []): ToolCall
    {
        return new ToolCall('call-'.(++self::$calls), $tool, $arguments);
    }

    /** @param array<string, mixed> $frontmatter */
    public static function write(string $path, string $body, array $frontmatter, string $summary): ToolCall
    {
        return self::call('write_page', ['path' => $path, 'frontmatter' => $frontmatter, 'body' => $body, 'summary' => $summary]);
    }

    /**
     * Records what every tool returned to the model during the test.
     *
     * @return \ArrayObject<int, array{tool: string, arguments: array, result: string}>
     */
    public static function recordToolResults(): \ArrayObject
    {
        $results = new \ArrayObject;

        Event::listen(ToolInvoked::class, function (ToolInvoked $event) use ($results) {
            $results[] = [
                'tool' => method_exists($event->tool, 'name') ? $event->tool->name() : $event->tool::class,
                'arguments' => $event->arguments,
                'result' => (string) $event->result,
            ];
        });

        return $results;
    }

    /** @param \ArrayObject<int, array{tool: string, arguments: array, result: string}> $results */
    public static function resultOf(\ArrayObject $results, string $tool, int $nth = 0): ?string
    {
        $matches = array_values(array_filter($results->getArrayCopy(), fn (array $r) => $r['tool'] === $tool));

        return $matches[$nth]['result'] ?? null;
    }
}
