<?php

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Development stand-ins for the services other parts of LaRecipe provide, in
 * exactly their contracts, so the reading UI can be built and tested in the
 * workbench on its own:
 *
 *  - the client search index (Search domain): {fields, storeFields, searchOptions, records}
 *  - Ask AI (Ask domain): a POST answered in the AI SDK UI message stream protocol
 *  - page feedback (Delivery domain): POST {path, version, locale, helpful, comment?}
 *
 * They are wired only for the fixture doc set; real sets use the real services.
 */
class FakeDocsServicesController
{
    public function searchIndex(string $locale): BinaryFileResponse
    {
        $file = dirname(__DIR__, 4).'/tests/Fixtures/track-b/_search.'.($locale === 'ar' ? 'ar' : 'en').'.json';

        return response()->file($file, ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=60']);
    }

    public function feedback(Request $request): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'version' => ['nullable', 'string'],
            'locale' => ['nullable', 'string'],
            'helpful' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json(['received' => true], 202);
    }

    /**
     * A scripted answer streamed like the real one: text deltas, then the pages
     * it cites as source-url parts. Questions about pricing get the honest
     * "not covered" answer with no sources.
     */
    public function ask(Request $request): StreamedResponse
    {
        $messages = (array) $request->input('messages', []);
        $last = end($messages) ?: [];
        $question = collect($last['parts'] ?? [])->where('type', 'text')->pluck('text')->implode(' ');
        $locale = (string) $request->input('locale', 'en');
        $base = $locale === 'en' ? '/guide/3.x' : "/guide/{$locale}/3.x";

        if (preg_match('/pric|cost|billing plan|not covered/i', $question) === 1) {
            $answer = "The docs do not cover pricing. LaRecipe is an open-source package, and these pages describe how to install and use it.";
            $sources = [];
        } else {
            $answer = "Add the MCP server to Claude Code with one command, then restart the session:\n\n```bash\nclaude mcp add --transport http larecipe https://larecipe.saleem.dev/docs/mcp\n```\n\nThe installer can do this for you: run `php artisan larecipe:install` and choose **Claude Code** when it asks which agents you use.";
            $sources = [
                ['url' => "{$base}/agents#the-mcp-server", 'title' => 'Connect your agents'],
                ['url' => "{$base}/installation#install-the-package", 'title' => 'Installation'],
            ];
        }

        return new StreamedResponse(function () use ($answer, $sources) {
            $send = function (array $chunk) {
                echo 'data: '.json_encode($chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();
            };

            $send(['type' => 'start', 'messageId' => 'msg_'.bin2hex(random_bytes(4))]);
            $send(['type' => 'text-start', 'id' => 'text_1']);

            foreach (preg_split('/(?<=\s)/u', $answer) ?: [] as $word) {
                $send(['type' => 'text-delta', 'id' => 'text_1', 'delta' => $word]);
                usleep(12000);
            }

            $send(['type' => 'text-end', 'id' => 'text_1']);

            foreach ($sources as $index => $source) {
                $send(['type' => 'source-url', 'sourceId' => 'src_'.$index, 'url' => $source['url'], 'title' => $source['title']]);
            }

            $send(['type' => 'finish']);
            echo "data: [DONE]\n\n";
            @ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'x-vercel-ai-ui-message-stream' => 'v1',
        ]);
    }
}
