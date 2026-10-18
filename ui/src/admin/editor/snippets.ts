import type { ComponentInfo } from '../types'

/**
 * The Markdown a component palette entry inserts: the tag with the props the
 * person filled in, and a body shaped like the component expects (Steps get
 * Step children, Tabs get Tab panels). Props are written the way LaRecipe's
 * parser reads them: strings quoted, booleans and numbers in braces.
 */

const CHILDREN: Record<string, { tag: string; props: Record<string, string>; body: string }> = {
  Steps: { tag: 'Step', props: { title: 'First step' }, body: 'What to do first.' },
  Tabs: { tag: 'Tab', props: { label: 'First tab' }, body: 'Content of the first tab.' },
  CardGroup: { tag: 'Card', props: { title: 'A card', href: '/' }, body: 'What the card links to.' },
  AccordionGroup: { tag: 'Accordion', props: { title: 'A question' }, body: 'The answer.' },
}

/** Components written on one line, with their text between the tags. */
const INLINE = new Set(['Badge', 'Button'])

/** Components that describe their content entirely through props. */
const SELF_CLOSING = new Set(['Video'])

const BODIES: Record<string, string> = {
  Mermaid: 'graph LR\n  Code --> Ticket --> Review --> Page',
  Math: 'e^{i\\pi} + 1 = 0',
  CodeGroup: '```bash title="npm"\nnpm install\n```\n\n```bash title="pnpm"\npnpm install\n```',
  FileTree: '- app/\n  - Models/\n    - User.php\n- routes/\n  - web.php',
  Frame: '![A screenshot](/_assets/screenshot.png)',
  Columns: 'First column.\n\nSecond column.',
}

export function formatProp(name: string, value: unknown): string | null {
  if (value === undefined || value === null || value === '') return null
  if (typeof value === 'boolean') return value ? name : `${name}={false}`
  if (typeof value === 'number') return `${name}={${value}}`
  return `${name}="${String(value).replace(/"/g, '\\"')}"`
}

function openTag(name: string, props: Record<string, unknown>, selfClose = false): string {
  const attributes = Object.entries(props)
    .map(([key, value]) => formatProp(key, value))
    .filter((attribute): attribute is string => attribute !== null)
  return `<${[name, ...attributes].join(' ')}${selfClose ? ' />' : '>'}`
}

export function componentSnippet(component: ComponentInfo, props: Record<string, unknown> = {}): string {
  const name = component.name

  if (SELF_CLOSING.has(name)) return `${openTag(name, props, true)}\n`
  if (INLINE.has(name)) return `${openTag(name, props)}${name === 'Badge' ? 'New' : 'Get started'}</${name}>`

  const child = CHILDREN[name]
  const body = child
    ? [1, 2].map((index) => `${openTag(child.tag, Object.fromEntries(Object.entries(child.props).map(([key, value]) => [key, index === 1 ? value : value.replace('First', 'Second').replace('A ', 'Another ')])))}\n${child.body}\n</${child.tag}>`).join('\n')
    : (BODIES[name] ?? 'Write the content here.')

  return `${openTag(name, props)}\n${body}\n</${name}>\n`
}

/** Prop values a palette form starts with: defaults, else the first allowed value for required props. */
export function initialProps(component: ComponentInfo): Record<string, unknown> {
  const props: Record<string, unknown> = {}
  for (const [name, spec] of Object.entries(component.props)) {
    if (spec.required) props[name] = spec.default ?? spec.enum?.[0] ?? ''
  }
  return props
}
