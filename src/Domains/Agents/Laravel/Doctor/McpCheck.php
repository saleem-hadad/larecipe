<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * The set's MCP endpoint answers tools/list with the docs tools.
 */
final class McpCheck implements Check
{
    public function run(DoctorContext $context): DoctorCheck
    {
        $set = $context->set;
        $url = $context->links->mcp($set);

        if ($url === null) {
            return DoctorCheck::warn('mcp', 'MCP endpoint', 'The web MCP endpoint is turned off for this set.', "Set mcp.web.enabled to true for the \"{$set->key}\" set, so remote agents can search and read the docs.");
        }

        $result = $context->probe->postJson($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        $tools = array_column((array) (json_decode($result->body, true)['result']['tools'] ?? []), 'name');

        if ($result->status === 401 || $result->status === 403) {
            return DoctorCheck::skip('mcp', 'MCP endpoint', "{$url} asks for authentication ({$result->status}); check it with a token.");
        }

        return in_array('search_docs', $tools, true) && in_array('read_page', $tools, true)
            ? DoctorCheck::pass('mcp', 'MCP endpoint', "{$url} serves ".implode(', ', $tools).'.')
            : DoctorCheck::fail('mcp', 'MCP endpoint', "{$url} answered {$result->status} without the docs tools.", 'Make sure the route is not behind CSRF or session middleware; MCP clients send JSON with a bearer token.');
    }
}
