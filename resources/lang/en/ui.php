<?php

/*
 * Strings for the reading experience. The Blade prerender and the React UI read
 * the same keys (React receives them in the boot payload), so a page never
 * changes its words when JavaScript takes over.
 *
 * Customer docs use plain words: "Updated", "Last change", "Page not found".
 * Kitchen words belong to LaRecipe's own surfaces, never here.
 */

return [
    'skip' => 'Skip to content',
    'agents_index' => 'Docs index for AI agents (llms.txt)',

    'header' => [
        'home' => ':name home',
        'menu' => 'Open navigation',
        'close_menu' => 'Close navigation',
        'repository' => 'Source repository',
        'version' => 'Version',
        'versions' => 'Versions',
        'language' => 'Language',
        'languages' => 'Languages',
        'theme' => 'Theme',
        'theme_light' => 'Light',
        'theme_dark' => 'Dark',
        'theme_system' => 'System',
    ],

    'search' => [
        'label' => 'Search the docs',
        'placeholder' => 'Search the docs',
        'open' => 'Open search',
    ],

    'nav' => [
        'label' => 'Docs',
        'updated' => 'Updated recently',
    ],

    'page' => [
        'breadcrumb' => 'Breadcrumb',
        'updated' => 'Updated :time',
        'updated_by' => 'Updated :time by :name',
        'reading' => '{1} :count min read|[2,*] :count min read',
        'fallback' => 'This page is not translated yet. You are reading the original.',
        'on_this_page' => 'On this page',
    ],

    'actions' => [
        'label' => 'For agents',
        'copy_markdown' => 'Copy as Markdown',
        'view_markdown' => 'View as Markdown',
        'open_claude' => 'Open in Claude',
        'open_chatgpt' => 'Open in ChatGPT',
        'open_cursor' => 'Open in Cursor',
        'connect_mcp' => 'Connect MCP',
        'copy_page' => 'Copy page',
        'more' => 'More page actions',
        'copied' => 'Copied',
        'mcp_copied' => 'MCP command copied',
        'prompt' => 'Read :url so I can ask questions about it.',
    ],

    'ticket' => [
        'label' => 'Last change',
        'pages' => '{1} :count page|[2,*] :count pages',
        'approved_by' => 'Approved by :name',
        'view' => 'View change',
        'summary_by' => ':name updated this page.',
        'summary' => 'This page was updated.',
    ],

    'footer' => [
        'previous' => 'Previous',
        'next' => 'Next',
        'pager' => 'Previous and next pages',
        'edit' => 'Edit this page',
        'credit' => 'Built with LaRecipe',
    ],

    'feedback' => [
        'question' => 'Was this page helpful?',
        'yes' => 'Yes',
        'no' => 'No',
        'comment' => 'What could be better? (optional)',
        'send' => 'Send',
        'skip' => 'Skip',
        'thanks' => 'Thanks. Your feedback was sent.',
        'error' => 'That did not send. Please try again.',
    ],

    'palette' => [
        'title' => 'Search',
        'placeholder' => 'Search pages, sections and actions',
        'pages' => 'Pages',
        'sections' => 'Sections',
        'actions' => 'Actions',
        'ask' => 'Ask AI about “:query”',
        'ask_label' => 'Ask',
        'empty' => 'No pages match “:query”.',
        'loading' => 'Loading the index',
        'error' => 'Search is not available right now.',
        'select' => 'to open',
        'navigate' => 'to move',
        'close' => 'to close',
        'switch_version' => 'Switch to version :version',
        'toggle_theme' => 'Switch theme',
        'copy_page' => 'Copy this page as Markdown',
        'view_markdown' => 'View this page as Markdown',
        'ask_docs' => 'Ask the docs',
        'results' => '{0} No results|{1} :count result|[2,*] :count results',
    ],

    'shortcuts' => [
        'title' => 'Keyboard shortcuts',
        'search' => 'Search',
        'search_alt' => 'Search',
        'ask' => 'Ask the docs',
        'help' => 'Show shortcuts',
        'close' => 'Close a panel',
    ],

    'ask' => [
        'title' => 'Ask the docs',
        'label' => 'Ask',
        'button' => 'Ask the docs',
        'placeholder' => 'Ask a question about these docs',
        'intro' => 'Answers come only from these docs and always cite the pages they used.',
        'sources' => 'Sources',
        'not_covered' => 'No page was cited for this answer, so the docs may not cover it yet.',
        'error' => 'Ask is not available right now. Try searching instead.',
        'send' => 'Send question',
        'stop' => 'Stop',
        'new' => 'New question',
        'close' => 'Close',
        'you' => 'You',
        'answer' => 'Answer',
        'search_instead' => 'Search for “:query”',
    ],

    'code' => [
        'copy' => 'Copy',
        'copied' => 'Copied',
        'copy_label' => 'Copy code',
    ],

    'anchor' => 'Link to this section',

    'frame' => [
        'zoom' => 'Zoom image',
        'close' => 'Close image',
    ],

    'video' => [
        'play' => 'Play video',
    ],

    'mermaid' => [
        'source' => 'Diagram source',
    ],

    'not_found' => [
        'label' => '404',
        'title' => 'Page not found',
        'body' => 'There is no page at :path.',
        'suggestions' => 'Similar pages',
        'home' => 'Back to :name',
    ],

    'common' => [
        'close' => 'Close',
        'loading' => 'Loading',
    ],
];
