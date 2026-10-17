<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends requests to the app from inside it, the way an agent would from outside,
 * so the doctor grades the real responses (headers, status codes, bodies)
 * without a running server.
 */
final class DocsProbe
{
    public function __construct(private readonly Kernel $kernel) {}

    /** @param array<string, string> $headers */
    public function get(string $url, array $headers = []): ProbeResult
    {
        return $this->send(Request::create($url, 'GET', server: $this->server($headers)));
    }

    /** @param array<string, string> $headers */
    public function postJson(string $url, array $body, array $headers = []): ProbeResult
    {
        return $this->send(Request::create($url, 'POST', server: $this->server(['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream'] + $headers), content: (string) json_encode($body)));
    }

    private function send(Request $request): ProbeResult
    {
        $response = $this->kernel->handle($request);

        return new ProbeResult($response->getStatusCode(), $response->headers->all(), $this->body($response));
    }

    private function body(Response $response): string
    {
        if (! $response instanceof StreamedResponse) {
            return (string) $response->getContent();
        }

        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /** @param array<string, string> $headers */
    private function server(array $headers): array
    {
        $server = ['HTTP_USER_AGENT' => 'LaRecipe-Doctor/1.0'];

        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_'.$key] = $value;
        }

        return $server;
    }
}
