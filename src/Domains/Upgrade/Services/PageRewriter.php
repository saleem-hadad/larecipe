<?php

namespace LaRecipe\Domains\Upgrade\Services;

use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;

/**
 * Rewrites one v2 page into v3 Markdown, deterministically, and says what it did.
 *
 *   > {warning} text                 > [!WARNING] alerts (GitHub syntax, portable)
 *   <a name="x"></a> + heading       a {#x} heading attribute
 *   <larecipe-card>                  <Card>, its content un-indented
 *   <larecipe-badge>/<larecipe-button> on their own line become <Badge>/<Button>;
 *                                    inside a sentence they become their text
 *   <larecipe-progress>              removed, with a report entry
 *   /{{route}}/{{version}}/page      a relative page.md link
 *
 * Code samples are never touched. Blade, custom Vue components and raw HTML are
 * reported for a person (or the larecipe-upgrade-v2 skill), never guessed at.
 */
final class PageRewriter
{
    private const ALERT = '/^(\s*)>\s*\{(primary|secondary|info|success|warning|danger)(?:\.([A-Za-z0-9_-]+))?\}\s?(.*)$/';

    private const ALERT_KINDS = ['primary' => 'IMPORTANT', 'secondary' => 'NOTE', 'info' => 'NOTE', 'success' => 'TIP', 'warning' => 'WARNING', 'danger' => 'CAUTION'];

    private const TONES = ['primary' => 'info', 'secondary' => 'neutral', 'info' => 'info', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'];

    /** Font Awesome names that differ in Lucide; the rest keep their name. */
    private const ICONS = ['close' => 'x', 'times' => 'x', 'comment' => 'message-square', 'paper-plane' => 'send', 'code-fork' => 'git-fork', 'address-card' => 'contact', 'bar-chart' => 'chart-column', 'cog' => 'settings', 'gear' => 'settings', 'envelope' => 'mail', 'pencil' => 'pencil', 'trash' => 'trash-2', 'exclamation-triangle' => 'triangle-alert', 'info-circle' => 'info', 'check-circle' => 'circle-check'];

    private const ANCHOR = '/^\s*<a\s+(?:name|id)\s*=\s*["\']([^"\']+)["\']\s*>\s*(?:<\/a>)?\s*$/i';

    private const BLADE = '/(?<![\w@`])@(if|elseif|else|endif|unless|endunless|foreach|endforeach|forelse|empty|endforelse|for|endfor|while|endwhile|php|endphp|include|section|endsection|yield|extends|component|endcomponent|auth|endauth|guest|endguest|can|endcan|isset|endisset|switch|endswitch|lang|csrf|json|verbatim|endverbatim|push|endpush|stack)\b/';

    /** @var list<ReportEntry> */
    private array $entries = [];

    private string $file = '';

    /** @var array<string, int> */
    private array $counts = [];

    /**
     * @param  array<string, string>  $files  page path => file inside the version, for link targets
     * @return array{title: ?string, description: ?string, body: string, entries: list<ReportEntry>}
     */
    public function rewrite(string $markdown, string $version, string $file, string $displayFile, V2Links $links, array $files): array
    {
        $this->entries = [];
        $this->counts = [];
        $this->file = $displayFile;
        $directory = dirname($file) === '.' ? '' : dirname($file);

        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));
        $out = [];
        $fence = null;

        for ($i = 0, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];

            if ($this->isFence($line, $fence)) {
                $out[] = $line;

                continue;
            }

            if ($fence !== null) {
                $out[] = $line;

                continue;
            }

            if (preg_match(self::ANCHOR, $line, $matches)) {
                $this->anchor($lines, $i, $matches[1]);

                continue;
            }

            if (preg_match(self::ALERT, $line, $matches)) {
                array_push($out, ...$this->alert($matches, $i));

                continue;
            }

            if (preg_match('/^\s*<larecipe-card\b([^>]*)>(.*)$/i', $line, $matches)) {
                $i = $this->card($lines, $i, $matches, $out, $version, $directory, $links, $files);

                continue;
            }

            if (preg_match('#^\s*</larecipe-card>\s*$#i', $line)) {
                $this->entries[] = ReportEntry::changed('components', 'Removed a </larecipe-card> that closed no card.', $this->file, $i + 1);

                continue;
            }

            if (preg_match('/^\s*<larecipe-(badge|button)\b([^>]*)>\s*$/i', $line, $matches) && ($end = $this->closing($lines, $i, "</larecipe-{$matches[1]}>")) !== null) {
                $this->count($matches[1] === 'badge' ? 'badges' : 'buttons');
                $out[] = $this->component($matches[1] === 'badge' ? 'Badge' : 'Button', $matches[2], implode(' ', array_slice($lines, $i + 1, $end - $i - 1)), $i);
                $i = $end;

                continue;
            }

            if (preg_match('/^\s*<(ul|ol)>\s*$/i', $line, $matches) && ($list = $this->htmlList($lines, $i, strtolower($matches[1]))) !== null) {
                array_push($out, '', ...$list['lines']);
                $out[] = '';
                $i = $list['end'];
                $this->count('lists');

                continue;
            }

            $converted = $this->line($line, $i, $version, $directory, $links, $files);

            if ($converted !== null) {
                $out[] = $converted;
            }
        }

        [$title, $description, $out] = $this->seed($out);
        $this->summarize();

        return [
            'title' => $title,
            'description' => $description,
            'body' => rtrim(implode("\n", $out))."\n",
            'entries' => $this->entries,
        ];
    }

    /** Tracks fenced code blocks; true when this line opens or closes one. */
    private function isFence(string $line, ?string &$fence): bool
    {
        if (! preg_match('/^\s{0,3}(`{3,}|~{3,})(.*)$/', $line, $matches)) {
            return false;
        }

        if ($fence === null) {
            $fence = $matches[1];

            return true;
        }

        if ($matches[1][0] === $fence[0] && strlen($matches[1]) >= strlen($fence) && trim($matches[2]) === '') {
            $fence = null;

            return true;
        }

        return false;
    }

    /** <a name="x"></a> before a heading becomes the heading's {#x}, so old #x links still land. */
    private function anchor(array &$lines, int $i, string $id): void
    {
        $next = $i + 1;

        while ($next < count($lines) && trim($lines[$next]) === '') {
            $next++;
        }

        if ($next < count($lines) && preg_match('/^\s{0,3}#{1,6}\s+\S/', $lines[$next])) {
            if (! preg_match('/\{#[^}]*\}\s*$/', $lines[$next])) {
                $lines[$next] = rtrim($lines[$next]).' {#'.$id.'}';
            }

            $this->count('anchors');

            return;
        }

        $this->entries[] = ReportEntry::manual('anchors', "Removed <a name=\"{$id}\"></a>, which is not followed by a heading. Links to #{$id} need a new target.", $this->file, $i + 1, "Add {#{$id}} to the heading or paragraph it should point at.");
    }

    /** @return list<string> */
    private function alert(array $matches, int $i): array
    {
        [, $indent, $type, $icon, $text] = $matches;
        $this->count('alerts');

        if ($icon !== '') {
            $this->entries[] = ReportEntry::dropped('alerts', "Dropped the {$icon} icon from a {$type} alert. GitHub alerts carry their own icon.", $this->file, $i + 1);
        }

        $lines = [$indent.'> [!'.self::ALERT_KINDS[$type].']'];

        if (trim($text) !== '') {
            $lines[] = $indent.'> '.$text;
        }

        return $lines;
    }

    /** Converts a <larecipe-card> block and returns the index of its last line. */
    private function card(array $lines, int $i, array $matches, array &$out, string $version, string $directory, V2Links $links, array $files): int
    {
        if (trim($matches[1]) !== '') {
            $this->entries[] = ReportEntry::dropped('components', 'Dropped card styling ('.trim($matches[1]).'). Cards follow the theme.', $this->file, $i + 1);
        }

        $content = [];
        $rest = $matches[2];

        if (preg_match('#^(.*?)</larecipe-card>\s*$#i', $rest, $inline)) {
            $content[] = $inline[1];
            $end = $i;
        } else {
            if (trim($rest) !== '') {
                $content[] = $rest;
            }

            for ($end = $i + 1; $end < count($lines) && ! preg_match('#^\s*</larecipe-card>\s*$#i', $lines[$end]); $end++) {
                $content[] = $lines[$end];
            }
        }

        $body = [];

        foreach ($this->dedent($content) as $offset => $line) {
            $converted = $this->line($line, $i + 1 + $offset, $version, $directory, $links, $files);

            if ($converted !== null && trim($converted) !== '') {
                $body[] = $converted;
            }
        }

        array_push($out, '<Card>', ...$body);
        $out[] = '</Card>';
        $this->count('cards');

        return min($end, count($lines) - 1);
    }

    /** The index of the line that closes a block opened at $start, or null when it never closes. */
    private function closing(array $lines, int $start, string $tag): ?int
    {
        for ($i = $start + 1; $i < count($lines); $i++) {
            if (strcasecmp(trim($lines[$i]), $tag) === 0) {
                return $i;
            }

            if (preg_match('/^\s*(`{3,}|~{3,})/', $lines[$i])) {
                return null;
            }
        }

        return null;
    }

    /**
     * A plain HTML list (<ul> or <ol> with one <li> per line) as a Markdown list,
     * since raw HTML is shown as text in v3. Anything richer is left for a person.
     *
     * @return array{lines: list<string>, end: int}|null
     */
    private function htmlList(array $lines, int $start, string $type): ?array
    {
        $end = $this->closing($lines, $start, "</{$type}>");

        if ($end === null) {
            return null;
        }

        $items = [];

        foreach (array_slice($lines, $start + 1, $end - $start - 1) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (! preg_match('#^\s*<li>(.*?)</li>\s*$#i', $line, $matches) || str_contains($matches[1], '<')) {
                return null;
            }

            $items[] = ($type === 'ol' ? (count($items) + 1).'. ' : '- ').trim($matches[1]);
        }

        return $items === [] ? null : ['lines' => $items, 'end' => $end];
    }

    /** One ordinary line: components, links, and what needs a person. Null drops the line. */
    private function line(string $line, int $i, string $version, string $directory, V2Links $links, array $files): ?string
    {
        if (preg_match('#^\s*<larecipe-(badge|button)\b([^>]*)>(.*?)</larecipe-\1>\s*$#i', $line, $matches) && ! str_contains($matches[3], '<')) {
            $this->count($matches[1] === 'badge' ? 'badges' : 'buttons');

            return $this->component($matches[1] === 'badge' ? 'Badge' : 'Button', $matches[2], $matches[3], $i);
        }

        if (preg_match('#^\s*<larecipe-progress\b[^>]*>\s*(?:</larecipe-progress>)?\s*$#i', $line)) {
            $this->entries[] = ReportEntry::dropped('components', 'Removed a <larecipe-progress> bar. v3 has no progress component.', $this->file, $i + 1);

            return null;
        }

        if (preg_match('#^\s*<br\s*/?>\s*$#i', $line)) {
            $this->count('line breaks');

            return '';
        }

        $line = $this->inlineComponents($line, $i);
        $line = $this->links($line, $version, $directory, $links, $files);
        $this->detect($line, $i);

        return $line;
    }

    /** A badge or button on its own line: a self-closing v3 component with its label as a prop. */
    private function component(string $name, string $attributes, string $label, int $i): string
    {
        $attributes = $this->attributes($attributes);
        $props = [];
        $label = trim((string) preg_replace('/\s+/', ' ', strip_tags($label)));

        if ($label !== '') {
            $props['label'] = $label;
        }

        if (isset($attributes['type'])) {
            $props['tone'] = self::TONES[$attributes['type']] ?? $attributes['type'];
        }

        if (isset($attributes['icon'])) {
            $props['icon'] = $this->icon($attributes['icon']);
        }

        if (isset($attributes['href'])) {
            $props['href'] = $attributes['href'];
        }

        $dropped = array_diff(array_keys($attributes), ['type', 'icon', 'href']);

        if ($dropped !== []) {
            $this->entries[] = ReportEntry::dropped('components', "Dropped {$name} styling attributes (".implode(', ', $dropped).'). Components follow the theme.', $this->file, $i + 1);
        }

        $rendered = implode(' ', array_map(fn (string $key, string $value) => $key.'="'.str_replace('"', '&quot;', $value).'"', array_keys($props), $props));

        return '<'.$name.($rendered === '' ? '' : ' '.$rendered).' />';
    }

    /** Badges, buttons and progress bars inside a sentence: v3 components are blocks, so they become their text. */
    private function inlineComponents(string $line, int $i): string
    {
        $flattened = preg_replace_callback('#<larecipe-(badge|button)\b[^>]*>(.*?)</larecipe-\1>#i', fn (array $m) => trim(strip_tags($m[2])), $line, -1, $flattenedCount);
        $flattened = preg_replace('#<larecipe-progress\b[^>]*>\s*(?:</larecipe-progress>)?#i', '', (string) $flattened, -1, $progressCount);

        if ($flattenedCount > 0) {
            $this->entries[] = ReportEntry::changed('components', 'A badge or button inside a sentence became plain text (components are blocks in v3).', $this->file, $i + 1);
        }

        if ($progressCount > 0) {
            $this->entries[] = ReportEntry::dropped('components', 'Removed a <larecipe-progress> bar. v3 has no progress component.', $this->file, $i + 1);
        }

        return $flattenedCount + $progressCount > 0 ? trim((string) $flattened) : $line;
    }

    /** Rewrites v2 docs links outside inline code. */
    private function links(string $line, string $version, string $directory, V2Links $links, array $files): string
    {
        $parts = preg_split('/(`+[^`]*`+)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$line];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                continue;
            }

            $parts[$index] = preg_replace_callback('/\]\(\s*([^)\s]+)((?:\s+"[^"]*")?)\s*\)/', function (array $m) use ($version, $directory, $links, $files) {
                $rewritten = $links->rewrite($m[1], $version, $directory, $files);

                if ($rewritten === null) {
                    return $m[0];
                }

                $this->count('links');

                return ']('.$rewritten.$m[2].')';
            }, $part) ?? $part;
        }

        return implode('', $parts);
    }

    /** Reports what the upgrade cannot convert: Blade, custom components, raw HTML. */
    private function detect(string $line, int $i): void
    {
        $code = preg_replace('/`+[^`]*`+/', '', $line) ?? $line;

        if (preg_match(self::BLADE, $code, $matches) || preg_match('/\{\{\s*\$|\{!!/', $code)) {
            $this->entries[] = ReportEntry::manual('blade', 'Blade in Markdown is not evaluated in v3: '.trim($line), $this->file, $i + 1, 'Replace it with a component, an include or plain Markdown (larecipe-upgrade-v2 skill).');
        }

        if (preg_match_all('#<([a-z][a-z0-9]*(?:-[a-z0-9]+)+)\b#', $code, $tags)) {
            foreach (array_unique($tags[1]) as $tag) {
                $this->entries[] = str_starts_with($tag, 'larecipe-')
                    ? ReportEntry::manual('components', "<{$tag}> could not be converted here. Rewrite it with a v3 component.", $this->file, $i + 1)
                    : ReportEntry::manual('vue', "<{$tag}> is a custom Vue component. v3 renders React components with a server fallback.", $this->file, $i + 1, 'Rewrite it as a React extension component and register it with LaRecipe::component() (larecipe-upgrade-v2 skill).');
            }
        } elseif (preg_match('#<(?!/?[A-Z])/?[a-z][a-z0-9]*(?:\s[^>]*)?/?>#', $code)) {
            $this->entries[] = ReportEntry::manual('html', 'Raw HTML is shown as text in v3: '.trim($line), $this->file, $i + 1, 'Use Markdown or a component, or set html_input to allow for this repository set.');
        }
    }

    /**
     * Seeds title and description from the first H1 and the paragraph after it,
     * and drops a hand-written table of contents (v3 builds one from headings).
     *
     * @param  list<string>  $lines
     * @return array{0: ?string, 1: ?string, 2: list<string>}
     */
    private function seed(array $lines): array
    {
        $title = null;
        $description = null;
        $fence = null;

        foreach ($lines as $index => $line) {
            if ($this->isFence($line, $fence) || $fence !== null) {
                continue;
            }

            if (preg_match('/^#\s+(.+?)\s*$/', $line, $matches)) {
                $title = trim((string) preg_replace('/\s*\{#[^}]*\}\s*$/', '', $matches[1]));
                [$description, $first, $after, $linked] = $this->paragraph($lines, $index + 1);
                $lines = $this->withoutManualToc($lines, $after);

                // The page header shows the description under the title, so the same
                // paragraph is not repeated in the article (unless it carries links).
                if ($description !== null && ! $linked) {
                    array_splice($lines, $first, $after - $first);
                    $this->entries[] = ReportEntry::changed('frontmatter', 'The opening paragraph became the page description, shown under the title.', $this->file, $first + 1);
                }

                break;
            }
        }

        return [$title, $description, $this->collapseBlankLines($lines)];
    }

    /**
     * @return array{0: ?string, 1: int, 2: int, 3: bool} the plain text of the paragraph after $start,
     *                                                    its first line, the line after it, and whether it links anywhere
     */
    private function paragraph(array $lines, int $start): array
    {
        $i = $start;

        while ($i < count($lines) && trim($lines[$i]) === '') {
            $i++;
        }

        $first = $i;
        $text = [];

        while ($i < count($lines) && trim($lines[$i]) !== '' && ! preg_match('/^\s*(#|>|[-*+]\s|\||<|`{3}|~{3}|\d+\.\s|---)/', $lines[$i])) {
            $text[] = trim($lines[$i]);
            $i++;
        }

        if ($text === []) {
            return [null, $start, $start, false];
        }

        $plain = implode(' ', $text);
        $linked = preg_match('/\]\(|<https?:/', $plain) === 1;
        $plain = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $plain);
        $plain = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $plain);
        $plain = (string) preg_replace('/[*_`]+/', '', $plain);
        $plain = trim((string) preg_replace('/\s+/', ' ', $plain));

        return [$plain === '' ? null : $plain, $first, $i, $linked];
    }

    /** No more than one blank line in a row outside code samples. */
    private function collapseBlankLines(array $lines): array
    {
        $kept = [];
        $fence = null;

        foreach ($lines as $line) {
            $code = $this->isFence($line, $fence) || $fence !== null;

            if (! $code && trim($line) === '' && $kept !== [] && trim(end($kept)) === '') {
                continue;
            }

            $kept[] = $line;
        }

        while ($kept !== [] && trim($kept[0]) === '') {
            array_shift($kept);
        }

        return $kept;
    }

    private function withoutManualToc(array $lines, int $from): array
    {
        $i = $from;
        $rule = null;

        while ($i < count($lines) && trim($lines[$i]) === '') {
            $i++;
        }

        if ($i < count($lines) && trim($lines[$i]) === '---') {
            $rule = $i++;

            while ($i < count($lines) && trim($lines[$i]) === '') {
                $i++;
            }
        }

        $start = $i;

        while ($i < count($lines) && preg_match('/^\s*[-*+]\s+\[[^\]]+\]\(#[^)]+\)\s*$/', $lines[$i])) {
            $i++;
        }

        if ($i === $start) {
            return $lines;
        }

        $this->entries[] = ReportEntry::changed('toc', 'Removed the hand-written table of contents. v3 builds one from the headings.', $this->file, $start + 1);

        return array_values(array_merge(array_slice($lines, 0, $rule ?? $start), array_slice($lines, $i)));
    }

    /** @return array<string, string> */
    private function attributes(string $source): array
    {
        preg_match_all('/([:@]?[A-Za-z_][\w.-]*)(?:\s*=\s*"([^"]*)"|\s*=\s*\'([^\']*)\')?/', $source, $matches, PREG_SET_ORDER);
        $attributes = [];

        foreach ($matches as $match) {
            $attributes[ltrim($match[1], ':@')] = ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '');
        }

        return $attributes;
    }

    private function icon(string $classes): string
    {
        $names = array_values(array_filter(preg_split('/\s+/', trim($classes)) ?: [], fn (string $class) => ! in_array($class, ['fa', 'fas', 'far', 'fab', 'fa-fw'], true)));
        $name = preg_replace('/^fa-/', '', $names[0] ?? $classes) ?? $classes;

        return self::ICONS[$name] ?? $name;
    }

    /** @param list<string> $lines @return list<string> */
    private function dedent(array $lines): array
    {
        $indent = null;

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $indent = min($indent ?? PHP_INT_MAX, strlen($line) - strlen(ltrim($line, ' ')));
            }
        }

        return array_map(fn (string $line) => substr($line, min((int) $indent, strlen($line) - strlen(ltrim($line, ' ')))), $lines);
    }

    private function count(string $what): void
    {
        $this->counts[$what] = ($this->counts[$what] ?? 0) + 1;
    }

    private function summarize(): void
    {
        $labels = [
            'alerts' => ['alert became a GitHub alert', 'alerts became GitHub alerts'],
            'anchors' => ['anchor became a heading id', 'anchors became heading ids'],
            'cards' => ['card became <Card>', 'cards became <Card>'],
            'badges' => ['badge became <Badge>', 'badges became <Badge>'],
            'buttons' => ['button became <Button>', 'buttons became <Button>'],
            'lists' => ['HTML list became a Markdown list', 'HTML lists became Markdown lists'],
            'links' => ['link became a relative .md link', 'links became relative .md links'],
            'line breaks' => ['standalone <br> line was removed', 'standalone <br> lines were removed'],
        ];

        $parts = [];

        foreach ($labels as $key => [$one, $many]) {
            if (isset($this->counts[$key])) {
                $parts[] = $this->counts[$key].' '.($this->counts[$key] === 1 ? $one : $many);
            }
        }

        if ($parts !== []) {
            array_unshift($this->entries, ReportEntry::changed('content', ucfirst(implode(', ', $parts)).'.', $this->file));
        }
    }
}
