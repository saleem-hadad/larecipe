<?php

namespace LaRecipe\Tests\Feature\Onboarding;

use Illuminate\Foundation\Application;
use LaRecipe\Domains\Onboarding\Actions\DetectAgentsAction;

/**
 * A folder of fake agent CLIs, so tests never run a real coding agent. The
 * fake `claude` records its arguments, writes one docs page and prints
 * stream-json events the way Claude Code does; the fake `codex` prints plain
 * progress.
 */
final class FakeAgents
{
    public readonly string $bin;

    /** @param list<string> $agents which fakes to create */
    public function __construct(array $agents = ['claude'])
    {
        $this->bin = sys_get_temp_dir().'/larecipe-fake-agents-'.bin2hex(random_bytes(4));
        mkdir($this->bin, 0755, true);

        foreach ($agents as $agent) {
            $this->script($agent, $agent === 'claude' ? self::claude() : self::plain($agent));
        }
    }

    /** Make LaRecipe look for agents in this folder only. */
    public function detectIn(Application $app): self
    {
        $app->bind(DetectAgentsAction::class, fn () => new DetectAgentsAction($this->bin));

        return $this;
    }

    public function script(string $name, string $body): void
    {
        file_put_contents($this->bin.'/'.$name, $body);
        chmod($this->bin.'/'.$name, 0755);
    }

    public function destroy(): void
    {
        foreach (glob($this->bin.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->bin);
    }

    private static function claude(): string
    {
        return <<<'SH'
            #!/bin/sh
            printf '%s\n' "$@" > "$PWD/claude-args.txt"
            mkdir -p docs/api
            cat > docs/api/authentication.md <<'MD'
            ---
            title: Authentication
            description: Authenticate API requests with a bearer token.
            sources: ["routes/api.php"]
            ---

            # Authentication

            Send a bearer token with every request.
            MD
            echo '{"type":"system","subtype":"init"}'
            echo '{"type":"assistant","message":{"content":[{"type":"text","text":"Reading the API routes."},{"type":"tool_use","name":"Write","input":{"file_path":"docs/api/authentication.md"}}]}}'
            echo '{"type":"result","result":"Wrote 1 page: docs/api/authentication.md"}'
            SH;
    }

    private static function plain(string $agent): string
    {
        return "#!/bin/sh\nprintf '%s\\n' \"\$@\" > \"\$PWD/{$agent}-args.txt\"\necho '{$agent} is working'\n";
    }
}
