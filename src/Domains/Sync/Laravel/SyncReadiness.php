<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Ai\Ai;
use LaRecipe\Domains\Sync\DTOs\SyncRequest;
use Throwable;

/**
 * Whether a `larecipe:sync` run can start, checked before any token is spent:
 * laravel/ai is installed (it is optional), the chosen provider exists in
 * config/ai.php and has a key, and a --remote run has its token.
 *
 * Every problem comes back as text that says exactly what to install or set.
 * Keys are only checked for presence; their values never leave this class.
 */
final class SyncReadiness
{
    /** Drivers that authenticate without an API key in config/ai.php. */
    private const KEYLESS = ['ollama', 'bedrock'];

    /** The variable laravel/ai's published config/ai.php reads each driver's key from. */
    private const KEY_VARIABLES = [
        'anthropic' => 'ANTHROPIC_API_KEY',
        'openai' => 'OPENAI_API_KEY',
        'gemini' => 'GEMINI_API_KEY',
        'azure' => 'AZURE_OPENAI_API_KEY',
        'cohere' => 'COHERE_API_KEY',
        'deepseek' => 'DEEPSEEK_API_KEY',
        'groq' => 'GROQ_API_KEY',
        'mistral' => 'MISTRAL_API_KEY',
        'openrouter' => 'OPENROUTER_API_KEY',
        'openai-compatible' => 'OPENAI_COMPATIBLE_API_KEY',
        'xai' => 'XAI_API_KEY',
    ];

    /** @param Closure(): bool $installed whether laravel/ai is installed (AiFeatures::available()) */
    public function __construct(
        private readonly Config $config,
        private readonly Closure $installed,
    ) {}

    public function installed(): bool
    {
        return (bool) ($this->installed)();
    }

    /** The provider a run uses: --provider, then larecipe.ai.sync.provider, then laravel/ai's default. */
    public function provider(SyncRequest $request): string
    {
        foreach ([$request->provider, $this->config->get('larecipe.ai.sync.provider'), $this->config->get('ai.default')] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return 'openai';
    }

    /** The model a run asks for: --model, then larecipe.ai.sync.model; null means the provider's default. */
    public function model(SyncRequest $request): ?string
    {
        foreach ([$request->model, $this->config->get('larecipe.ai.sync.model')] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /** "anthropic/claude-sonnet-4-5": the agent, as the confirmation and the commit trailer name it. */
    public function agentName(SyncRequest $request): string
    {
        $provider = $this->provider($request);

        return $provider.'/'.($this->model($request) ?? $this->defaultModel($provider) ?? 'default model');
    }

    /** Why the run cannot start, or null when it can. */
    public function problem(SyncRequest $request): ?string
    {
        if (! $this->installed()) {
            return self::installMessage();
        }

        $provider = $this->provider($request);
        $settings = $this->config->get("ai.providers.{$provider}");

        if (! is_array($settings)) {
            $known = array_keys((array) $this->config->get('ai.providers', []));

            return "There is no [{$provider}] provider in config/ai.php.".($known !== [] ? ' Providers: '.implode(', ', $known).'.' : '')
                .' Choose one with --provider or LARECIPE_AI_PROVIDER.';
        }

        $driver = (string) ($settings['driver'] ?? $provider);
        $key = $settings['key'] ?? null;

        if (! in_array($driver, self::KEYLESS, true) && (! is_string($key) || trim($key) === '')) {
            $variable = self::KEY_VARIABLES[$driver] ?? null;

            return "The [{$provider}] provider has no API key. "
                .($variable !== null ? "Set {$variable} in .env (config/ai.php reads it)" : "Set providers.{$provider}.key in config/ai.php")
                .', or choose another provider with --provider.';
        }

        if ($request->remote !== null) {
            $token = $this->config->get('larecipe.ai.sync.remote_token');

            if (! is_string($token) || $token === '') {
                return McpRemoteAuthorTools::missingToken();
            }
        }

        return null;
    }

    public static function installMessage(): string
    {
        return implode("\n", [
            'larecipe:sync runs an AI agent on your own provider key through laravel/ai, which is not installed. Install it with:',
            '',
            '  composer require laravel/ai',
            '  php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"',
            '',
            'Then add a provider key to .env (for example ANTHROPIC_API_KEY) and choose the provider with LARECIPE_AI_PROVIDER=anthropic.',
            'You may not need it: your own coding agent can update the docs with the larecipe-update-docs skill, at no extra cost.',
        ]);
    }

    /** The provider's default text model, or null when laravel/ai cannot say. */
    private function defaultModel(string $provider): ?string
    {
        if (! $this->installed()) {
            return null;
        }

        try {
            return Ai::textProvider($provider)->defaultTextModel();
        } catch (Throwable) {
            return null;
        }
    }
}
