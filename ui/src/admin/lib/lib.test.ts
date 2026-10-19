import { describe, expect, it } from 'vitest'
import frontmatter from '../../../../resources/schemas/frontmatter.schema.json'
import manifest from '../../../../resources/schemas/larecipe.schema.json'
import navigation from '../../../../resources/schemas/navigation.schema.json'
import { componentSnippet, formatProp, initialProps } from '../editor/snippets'
import { slugify } from '../screens/NewPage'
import type { JsonSchema } from '../types'
import { diffLines, diffStats, withContext } from './diff'
import { actorName, relativeTime, revisionVerb } from './format'
import { fromNavJson, groupForPath, insert, listedPaths, move, nudge, pageNode, remove, toNavJson } from './navigation'
import { labelFor, prune, validate, variantFor, variantsOf } from './schema'
import { revisionFixture } from '../test/harness'

describe('line diffs', () => {
  it('marks added and removed lines with their line numbers', () => {
    const diff = diffLines('# Refunds\n\nMoney back.\n', '# Refunds\n\nMoney back within 30 days.\nAsk support.\n')

    expect(diff.map((line) => [line.type, line.text])).toEqual([
      ['same', '# Refunds'],
      ['same', ''],
      ['remove', 'Money back.'],
      ['add', 'Money back within 30 days.'],
      ['add', 'Ask support.'],
    ])
    expect(diffStats(diff)).toEqual({ additions: 2, deletions: 1 })
    expect(diff[3]).toMatchObject({ oldLine: null, newLine: 3 })
  })

  it('treats a missing side as all added or all removed', () => {
    expect(diffStats(diffLines('', 'a\nb\n'))).toEqual({ additions: 2, deletions: 0 })
    expect(diffStats(diffLines('a\nb\n', ''))).toEqual({ additions: 0, deletions: 2 })
  })

  it('folds long unchanged stretches into gaps', () => {
    const before = Array.from({ length: 30 }, (_, i) => `line ${i}`).join('\n')
    const after = before.replace('line 15', 'line fifteen')
    const chunks = withContext(diffLines(before, after), 2)

    expect(chunks.map((chunk) => chunk.type)).toEqual(['gap', 'lines', 'gap'])
    expect(chunks[1]).toMatchObject({ type: 'lines' })
    expect(chunks[0]).toEqual({ type: 'gap', count: 13 })
  })
})

describe('schema forms', () => {
  const fm = frontmatter as JsonSchema
  const mf = manifest as JsonSchema
  const nav = navigation as JsonSchema

  it('accepts valid frontmatter and points at the field that is wrong', () => {
    expect(validate({ title: 'Refunds', sources: ['app/Billing/**'], hidden: true, badge: { label: 'New', tone: 'info' } }, fm)).toEqual([])

    const errors = validate({ title: '', layout: 'wider', visibility: 'secret' }, fm)
    expect(errors.map((error) => error.path)).toEqual(['title', 'layout', 'visibility'])
    expect(errors[0]!.message).toBe('Must not be empty.')
  })

  it('checks larecipe.json, nested objects and lists included', () => {
    expect(validate({ name: 'KB', theme: { accent: '#0f766e', radius: 'soft' }, versions: ['2.x', { id: '1.x', status: 'archived' }] }, mf)).toEqual([])

    const errors = validate({ name: 'KB', theme: { radius: 'round', glow: true }, redirects: [{ from: 'old', to: '/new' }] }, mf)
    expect(errors.map((error) => error.path)).toEqual(['theme/radius', 'theme/glow', 'redirects/0/from'])
  })

  it('validates navigation.json through its if/then branches and $refs', () => {
    expect(validate({ groups: ['index', { group: 'Billing', pages: ['billing/refunds', { page: 'billing/exports', label: 'CSV' }] }] }, nav)).toEqual([])
    expect(validate({ groups: [{ group: 'Billing', pages: [{ page: '' }] }] }, nav)).not.toEqual([])
    expect(validate({ groups: [], extra: true }, nav).map((error) => error.path)).toEqual(['extra'])
  })

  it('offers the shapes a field may take', () => {
    const sources = (fm.properties as Record<string, JsonSchema>).sources!
    const variants = variantsOf(sources, fm)

    expect(variants.map((variant) => variant.label)).toEqual(['Text', 'List'])
    expect(variantFor(['a'], variants, fm)?.label).toBe('List')
    expect(variantFor('a', variants, fm)?.label).toBe('Text')
    expect(variantsOf((fm.properties as Record<string, JsonSchema>).inserts!, fm).map((variant) => variant.label)).toEqual(['Off', 'Fields'])
  })

  it('labels keys for people and drops empty values before saving', () => {
    expect(labelFor('redirect_from')).toBe('Redirect from')
    expect(labelFor('docsPath')).toBe('Docs path')
    expect(labelFor('llmsTxt')).toBe('llms.txt')
    expect(prune({ name: 'KB', description: '', theme: { accent: '' }, versions: [], logo: { light: '/a.svg' } })).toEqual({ name: 'KB', logo: { light: '/a.svg' } })
  })
})

describe('the navigation model', () => {
  const json = { groups: ['index', { group: 'Billing', pages: ['billing/refunds', { page: 'billing/exports', label: 'CSV' }] }, { label: 'Status', href: 'https://status.acme.dev' }] }

  it('round-trips navigation.json', () => {
    expect(toNavJson(fromNavJson(json))).toEqual(json)
    expect(listedPaths(fromNavJson(json).items)).toEqual(['index', 'billing/refunds', 'billing/exports'])
  })

  it('keeps other tabs as they are', () => {
    const tabs = { tabs: [{ label: 'Guides', groups: ['index'] }, { label: 'API', href: '/api' }] }
    expect(toNavJson(fromNavJson(tabs))).toEqual(tabs)
  })

  it('moves a page out of a group, before another page, and into a group', () => {
    const model = fromNavJson(json)
    const billing = model.items[1]!
    const exports = billing.kind === 'group' ? billing.children[1]! : billing

    const before = move(model.items, exports.id, { target: model.items[0]!.id, position: 'before' })
    expect(toNavJson({ ...model, items: before }).groups).toEqual([{ page: 'billing/exports', label: 'CSV' }, 'index', { group: 'Billing', pages: ['billing/refunds'] }, { label: 'Status', href: 'https://status.acme.dev' }])

    const inside = move(before, model.items[0]!.id, { target: billing.id, position: 'inside' })
    expect(listedPaths(inside)).toEqual(['billing/exports', 'billing/refunds', 'index'])
  })

  it('never drops a group into itself', () => {
    const model = fromNavJson(json)
    const billing = model.items[1]!
    const refunds = billing.kind === 'group' ? billing.children[0]! : billing

    expect(move(model.items, billing.id, { target: refunds.id, position: 'after' })).toBe(model.items)
  })

  it('nudges, removes and inserts', () => {
    const model = fromNavJson(json)
    expect(listedPaths(nudge(model.items, model.items[0]!.id, 1))).toEqual(['billing/refunds', 'billing/exports', 'index'])

    const [without, removed] = remove(model.items, model.items[0]!.id)
    expect(removed).toMatchObject({ kind: 'page', path: 'index' })
    expect(listedPaths(insert(without, pageNode('faq'), { target: null, position: 'end' }))).toEqual(['billing/refunds', 'billing/exports', 'faq'])
  })

  it('finds the group a new page belongs in from its folder', () => {
    const model = fromNavJson(json)
    expect(groupForPath(model.items, 'billing/disputes')).toBe(model.items[1]!.id)
    expect(groupForPath(model.items, 'faq')).toBeNull()
  })
})

describe('component snippets', () => {
  const callout = { name: 'Callout', description: null, props: { type: { type: 'string', default: 'note', enum: ['note', 'warning'] }, title: { type: 'string' } }, island: false, raw: false, origin: 'builtin' }

  it('writes props the way the parser reads them', () => {
    expect(formatProp('title', 'Say "hi"')).toBe('title="Say \\"hi\\""')
    expect(formatProp('cols', 3)).toBe('cols={3}')
    expect(formatProp('open', true)).toBe('open')
    expect(formatProp('open', false)).toBe('open={false}')
    expect(formatProp('title', '')).toBeNull()
  })

  it('inserts a component with its props and a body shaped like it expects', () => {
    expect(componentSnippet(callout, { type: 'warning', title: 'Heads up' })).toBe('<Callout type="warning" title="Heads up">\nWrite the content here.\n</Callout>\n')
    expect(componentSnippet({ ...callout, name: 'Steps', props: {} })).toContain('<Step title="First step">')
    expect(componentSnippet({ ...callout, name: 'Steps', props: {} })).toContain('<Step title="Second step">')
    expect(initialProps({ ...callout, props: { href: { type: 'string', required: true } } })).toEqual({ href: '' })
  })
})

describe('words', () => {
  it('slugs a title into a path', () => {
    expect(slugify('Refund policy (EU)')).toBe('refund-policy-eu')
    expect(slugify('Billing / Café exports')).toBe('billing/cafe-exports')
  })

  it('says what a revision did and who did it', () => {
    expect(revisionVerb(revisionFixture())).toBe('Updated billing/refunds')
    expect(revisionVerb(revisionFixture({ based_on_hash: 'absent' }))).toBe('Created billing/refunds')
    expect(revisionVerb(revisionFixture({ operation: 'move', ref_before: { set: 'kb', version: 'current', locale: 'en', path: 'old' } }))).toBe('Moved old to billing/refunds')
    expect(actorName({ actor: 'migration', name: 'claude-code', ref: null, at: null, approved_by: null })).toBe('claude-code (docs migration)')
    expect(relativeTime('2026-10-17T10:00:00Z', new Date('2026-10-17T12:00:00Z'))).toBe('2 hours ago')
  })
})
