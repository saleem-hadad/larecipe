<?php

namespace LaRecipe\Domains\Agents\Laravel;

use Illuminate\Routing\Router;
use Laravel\Mcp\Server\Registrar;
use LaRecipe\Domains\Agents\Controllers\AskEndpointController;
use LaRecipe\Domains\Agents\Controllers\LlmsFullController;
use LaRecipe\Domains\Agents\Controllers\LlmsTxtController;
use LaRecipe\Domains\Agents\Controllers\SkillController;
use LaRecipe\Domains\Agents\Controllers\SkillFileController;
use LaRecipe\Domains\Agents\Controllers\WellKnownSkillsController;
use LaRecipe\Domains\Agents\Mcp\DocsServer;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Delivery\Controllers\FeedbackController;
use LaRecipe\Domains\Delivery\Controllers\SitemapController;

/**
 * The agent surfaces' routes.
 *
 * Per set (inside the set's group, so its prefix, domain and middleware apply,
 * and before the page catch-all): llms.txt, llms-full.txt, skill.md, skills/,
 * the opt-in /ask endpoint, plus Delivery's feedback endpoint and sitemap.
 *
 * Global: /.well-known/agent-skills/index.json and one MCP endpoint per set.
 * MCP endpoints sit outside the set group on purpose: MCP clients post JSON
 * without a session or CSRF token, and authenticate with bearer tokens
 * (mcp.web.middleware), so the web group would only get in their way.
 */
final class AgentRoutes
{
    public function __construct(private readonly Registrar $mcp) {}

    public function forSet(Router $router, SetConfig $set): void
    {
        if ($set->setting('agents.llms_txt', true)) {
            $router->get('llms.txt', LlmsTxtController::class)->name('llms');
            $router->get('{scope}/llms.txt', LlmsTxtController::class)->where('scope', '.+')->name('llms.scoped');
        }

        if ($set->setting('agents.llms_full', true)) {
            $router->get('llms-full.txt', LlmsFullController::class)->name('llms-full');
            $router->get('{scope}/llms-full.txt', LlmsFullController::class)->where('scope', '.+')->name('llms-full.scoped');
        }

        if ($set->setting('agents.skill_md', true)) {
            $router->get('skill.md', SkillController::class)->name('skill');
            $router->get('skills/{name}/{file}', SkillFileController::class)
                ->where('name', '[a-z0-9]+(?:-[a-z0-9]+)*')
                ->where('file', 'SKILL\.md|(?:references|scripts|assets)/.+')
                ->name('skills.file');
        }

        if ($set->setting('agents.ask_endpoint', false)) {
            $router->get('ask', AskEndpointController::class)->name('ask-endpoint');
        }

        if ($set->setting('agents.sitemap', true)) {
            $router->get('sitemap.xml', SitemapController::class)->name('sitemap');
        }

        if ($set->setting('feedback.enabled', (bool) config('larecipe.feedback.enabled', true))) {
            $router->post('_feedback', FeedbackController::class)->middleware('throttle:larecipe-feedback')->name('feedback');
        }
    }

    /** @param array<string, SetConfig> $sets */
    public function global(Router $router, array $sets): void
    {
        if (array_filter($sets, fn (SetConfig $set) => $set->setting('agents.skill_md', true) && $set->setting('agents.well_known_skills', true)) !== []) {
            $router->get('.well-known/agent-skills/index.json', WellKnownSkillsController::class)->name('larecipe.agent-skills');
        }

        foreach ($sets as $set) {
            if (! $set->setting('mcp.web.enabled', true)) {
                continue;
            }

            $path = trim($set->prefix.'/'.trim((string) $set->setting('mcp.web.path', 'mcp'), '/'), '/');

            $router->group(array_filter(['domain' => $set->domain]), function () use ($set, $path) {
                $this->mcp->web($path, DocsServer::class)
                    ->middleware([...(array) $set->setting('mcp.web.middleware', []), 'throttle:larecipe-mcp', BindMcpSet::class.':'.$set->key])
                    ->defaults('larecipeSet', $set->key)
                    ->name("larecipe.{$set->key}.mcp");
            });
        }
    }
}
