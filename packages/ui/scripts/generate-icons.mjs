/**
 * Generates LaRecipe's icon set from Lucide (ISC license).
 *
 * One source, two outputs, so the Blade prerender and the React UI draw the
 * exact same SVG (same geometry, same stroke) and the takeover never changes a
 * pixel:
 *
 *   packages/ui/src/icons/nodes.ts            used by the Icons namespace in @larecipe/ui
 *   resources/views/partials/icon.blade.php   used by the server-rendered chrome
 *
 * A third output holds the icons authors may name in content (<Card icon="rocket">),
 * drawn on the server only:
 *
 *   src/Domains/Markdown/Support/ContentIcons.php
 *
 * Run with `pnpm --filter @larecipe/ui icons` after changing the list below.
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const require = createRequire(import.meta.url)
const lucideRoot = dirname(require.resolve('lucide-react/package.json'))

/** Export name => Lucide icon name. Directional icons mirror in right-to-left pages. */
const ICONS = {
  ArrowDown: 'arrow-down',
  ArrowLeft: 'arrow-left',
  ArrowRight: 'arrow-right',
  ArrowUp: 'arrow-up',
  ArrowUpRight: 'arrow-up-right',
  Bell: 'bell',
  BookOpen: 'book-open',
  Calendar: 'calendar',
  Check: 'check',
  ChevronDown: 'chevron-down',
  ChevronLeft: 'chevron-left',
  ChevronRight: 'chevron-right',
  CircleAlert: 'circle-alert',
  CircleCheck: 'circle-check',
  Clock: 'clock',
  Code: 'code',
  Copy: 'copy',
  CornerDownLeft: 'corner-down-left',
  Download: 'download',
  ExternalLink: 'external-link',
  File: 'file',
  FileText: 'file-text',
  Folder: 'folder',
  Globe: 'globe',
  Hash: 'hash',
  Info: 'info',
  Keyboard: 'keyboard',
  Languages: 'languages',
  Lightbulb: 'lightbulb',
  Link: 'link',
  List: 'list',
  Mail: 'mail',
  Menu: 'menu',
  MessageCircle: 'message-circle',
  Minus: 'minus',
  Monitor: 'monitor',
  Moon: 'moon',
  OctagonAlert: 'octagon-alert',
  Play: 'play',
  Plug: 'plug',
  Plus: 'plus',
  RotateCcw: 'rotate-ccw',
  Search: 'search',
  Settings: 'settings',
  Square: 'square',
  SquarePen: 'square-pen',
  Sun: 'sun',
  Terminal: 'terminal',
  TextAlignStart: 'text-align-start',
  ThumbsDown: 'thumbs-down',
  ThumbsUp: 'thumbs-up',
  TriangleAlert: 'triangle-alert',
  User: 'user',
  X: 'x',
  ZoomIn: 'zoom-in',
}

/** Icons authors may name in content, by their Lucide name. Server-rendered only. */
const CONTENT_ICONS = [
  'accessibility', 'activity', 'bell', 'blocks', 'book', 'book-open', 'bot', 'box', 'brain', 'bug', 'calendar',
  'chart-column', 'circle-check', 'circle-question-mark', 'clipboard-list', 'clock', 'cloud', 'code', 'compass', 'cpu',
  'credit-card', 'database', 'download', 'eye', 'file-code', 'file-text', 'flag', 'folder', 'gauge', 'gift',
  'git-branch', 'git-merge', 'git-pull-request', 'globe', 'graduation-cap', 'hammer', 'heart', 'house', 'image',
  'inbox', 'key', 'languages', 'layers', 'layout-grid', 'life-buoy', 'lightbulb', 'link', 'lock', 'mail', 'map',
  'megaphone', 'message-circle', 'monitor', 'package', 'palette', 'pen-line', 'plug', 'puzzle', 'refresh-cw',
  'rocket', 'scale', 'search', 'send', 'server', 'settings', 'shield-check', 'shopping-cart', 'smartphone',
  'sparkles', 'star', 'terminal', 'timer', 'trophy', 'upload', 'user', 'users', 'wand-sparkles', 'workflow',
  'wrench', 'zap',
]

const MIRRORED = new Set(['ArrowLeft', 'ArrowRight', 'ArrowUpRight', 'ChevronLeft', 'ChevronRight', 'CornerDownLeft', 'ExternalLink', 'TextAlignStart'])

/**
 * Lucide 1.x dropped brand icons. The repository link keeps Lucide's last
 * "github" drawing (ISC, lucide 0.4x) so the header still reads as a source link.
 */
const EXTRA = {
  Github: [
    ['path', { d: 'M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4' }],
    ['path', { d: 'M9 18c-4.51 2-5-2-7-2' }],
  ],
}

function nodesFor(name) {
  const source = readFileSync(resolve(lucideRoot, `dist/esm/icons/${name}.mjs`), 'utf8')
  const match = source.match(/const __iconData = (\{[\s\S]*?\});\n/)
  if (!match) throw new Error(`Could not read Lucide icon ${name}`)
  // The icon literal is plain data: a name, tag names and string attributes.
  const data = Function(`"use strict"; return (${match[1]})`)()
  return data.node.map(([tag, attrs]) => {
    const { key: _key, ...rest } = attrs
    return [tag, rest]
  })
}

const kebab = (value) => value.replace(/[A-Z]/g, (char) => `-${char.toLowerCase()}`)
const escapeAttr = (value) => String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;')

const all = {}
for (const [exportName, lucideName] of Object.entries(ICONS)) all[exportName] = nodesFor(lucideName)
for (const [exportName, nodes] of Object.entries(EXTRA)) all[exportName] = nodes

const names = Object.keys(all).sort()
const license = 'Icon geometry from Lucide (https://lucide.dev), ISC License. Generated by packages/ui/scripts/generate-icons.mjs; do not edit by hand.'

const ts = [
  `// ${license}`,
  '',
  "export type IconNode = ReadonlyArray<readonly [string, Readonly<Record<string, string>>]>",
  '',
  'export const iconNodes = {',
  ...names.map((name) => `  ${name}: ${JSON.stringify(all[name])},`),
  '} as const satisfies Record<string, IconNode>',
  '',
  'export type IconName = keyof typeof iconNodes',
  '',
  `export const mirroredIcons: ReadonlySet<IconName> = new Set<IconName>(${JSON.stringify([...MIRRORED].sort())})`,
  '',
].join('\n')

writeFileSync(resolve(here, '../src/icons/nodes.ts'), ts)

const svgInner = (nodes) => nodes.map(([tag, attrs]) => `<${tag} ${Object.entries(attrs).map(([k, v]) => `${kebab(k)}="${escapeAttr(v)}"`).join(' ')}/>`).join('')

const php = [
  `{{-- ${license} --}}`,
  '{{-- Usage: @include(\'larecipe::partials.icon\', [\'name\' => \'Search\', \'iconSize\' => 16, \'iconClass\' => \'\']) --}}',
  '@php',
  '    $__lrIcons = [',
  ...names.map((name) => `        '${name}' => '${svgInner(all[name]).replace(/'/g, "\\'")}',`),
  '    ];',
  `    $__lrMirrored = ${JSON.stringify([...MIRRORED].sort()).replace(/"/g, "'")};`,
  '    $__lrSize = $iconSize ?? 16;',
  "    $__lrClass = trim('lr-icon '.(in_array($name, $__lrMirrored, true) ? 'lr-icon-dir ' : '').($iconClass ?? ''));",
  '@endphp',
  // No trailing newline: icons sit inline next to text, and the markup must match React's exactly.
  '<svg class="{{ $__lrClass }}" xmlns="http://www.w3.org/2000/svg" width="{{ $__lrSize }}" height="{{ $__lrSize }}" viewBox="0 0 24 24" fill="{{ $iconFill ?? \'none\' }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $__lrIcons[$name] ?? \'\' !!}</svg>',
].join('\n')

writeFileSync(resolve(here, '../../../resources/views/partials/icon.blade.php'), php)

const contentNames = [...CONTENT_ICONS].sort()
const quote = (value) => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`
const contentPhp = [
  '<?php',
  '',
  'namespace LaRecipe\\Domains\\Markdown\\Support;',
  '',
  '/**',
  ' * The icons authors may name in content, such as <Card icon="rocket">, by',
  ' * their Lucide name. Unknown names draw nothing.',
  ' *',
  ` * ${license}`,
  ' */',
  'final class ContentIcons',
  '{',
  '    /** @var array<string, string> Lucide name => SVG children */',
  '    public const NODES = [',
  ...contentNames.map((name) => `        ${quote(name)} => ${quote(svgInner(nodesFor(name)))},`),
  '    ];',
  '',
  '    /** A Lucide name, or its PascalCase export name (BookOpen), as the key in NODES. */',
  '    public static function normalize(string $name): string',
  '    {',
  "        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', trim($name)));",
  '    }',
  '',
  '    public static function svg(string $name, int $size = 20, string $class = \'lr-icon\'): ?string',
  '    {',
  '        $nodes = self::NODES[self::normalize($name)] ?? null;',
  '',
  '        if ($nodes === null) {',
  '            return null;',
  '        }',
  '',
  '        return \'<svg class="\'.htmlspecialchars($class, ENT_QUOTES).\'" xmlns="http://www.w3.org/2000/svg" width="\'.$size.\'" height="\'.$size.\'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">\'.$nodes.\'</svg>\';',
  '    }',
  '}',
  '',
].join('\n')

writeFileSync(resolve(here, '../../../src/Domains/Markdown/Support/ContentIcons.php'), contentPhp)

console.log(`Generated ${names.length} chrome icons and ${contentNames.length} content icons.`)
