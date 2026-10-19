<?php

/*
 * Agents that read the HTML (not the headers) find llms.txt in the page body,
 * outside navigation, the way larecipe:doctor checks it.
 */

it('links to llms.txt in the page body, out of the tab order', function () {
    $html = $this->get('/docs/installation')->assertOk()->getContent();

    expect($html)->toMatch('#<a class="lr-sr" href="http://localhost/docs/llms\.txt" tabindex="-1">Docs index for AI agents \(llms\.txt\)</a>#');
});
