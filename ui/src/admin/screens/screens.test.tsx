import { act, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { EditorWorkspace } from '../editor/EditorWorkspace'
import { PREVIEW_DELAY } from '../editor/useLivePreview'
import { bootFixture, mockApi, renderAdmin, revisionFixture } from '../test/harness'
import type { RevisionDetail } from '../types'
import { Review } from './Review'
import { Settings } from './Settings'
import { Ticket } from './Ticket'

beforeAll(() => {
  // CodeMirror measures layout; jsdom has no layout, so give it empty rectangles.
  Range.prototype.getClientRects = () => ({ length: 0, item: () => null, [Symbol.iterator]: [][Symbol.iterator] }) as unknown as DOMRectList
  Range.prototype.getBoundingClientRect = () => new DOMRect()
})

afterEach(() => {
  vi.unstubAllGlobals()
  vi.useRealTimers()
  document.body.innerHTML = ''
})

const conflict = revisionFixture({ id: '7', ticket: '0007', status: 'conflict', is_conflict: true, channel: 'migration', migration: '2026_10_17_141502_docs_pagerduty', author: { actor: 'migration', name: 'claude-code', ref: 'https://github.com/acme/acme/pull/482', at: null, approved_by: null }, title: 'Escalation', summary: 'Document PagerDuty escalation', ref: { set: 'kb', version: 'current', locale: 'en', path: 'support/escalation' } })
const draft = revisionFixture()

describe('the pass', () => {
  it('lists conflicts first, then drafts, as tickets, and counts what waits', async () => {
    mockApi({ 'GET review': { mode: 'required', conflicts: [conflict], drafts: [draft], recent: [] } })
    renderAdmin(<Review />)

    const conflicts = await screen.findByRole('heading', { name: /Conflicts · 1/ })
    const drafts = screen.getByRole('heading', { name: /Drafts · 1/ })
    expect(conflicts.compareDocumentPosition(drafts) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(screen.getByRole('article', { name: 'Ticket 0007: Escalation' }).textContent).toContain('2026_10_17_141502_docs_pagerduty')
  })

  it('publishes a draft from its ticket, signed off by the reviewer', async () => {
    const api = mockApi({
      'GET review': { mode: 'required', conflicts: [], drafts: [draft], recent: [] },
      'POST review/5/publish': { outcome: 'published', message: null, revision: { ...draft, status: 'published' }, validation: { ok: true, summary: { pages: 1, errors: 0, warnings: 0 }, issues: [] } },
    })
    const { notices } = renderAdmin(<Review />)

    const ticket = await screen.findByRole('article', { name: 'Ticket 0005: Refunds' })
    fireEvent.click(within(ticket).getByRole('button', { name: 'Publish' }))

    await waitFor(() => expect(api.calls.some((call) => call.method === 'POST' && call.path === 'review/5/publish')).toBe(true))
    await waitFor(() => expect(notices.push).toHaveBeenCalledWith({ message: 'Ticket 0005 is live, signed off by Ada Reviewer.', tone: 'success' }))
  })

  it('rejects a draft with a reason the agent can read', async () => {
    const api = mockApi({
      'GET review': { mode: 'required', conflicts: [], drafts: [draft], recent: [] },
      'POST review/5/reject': { outcome: 'rejected', revision: { ...draft, status: 'rejected' } },
    })
    renderAdmin(<Review />)

    fireEvent.click(within(await screen.findByRole('article')).getByRole('button', { name: 'Reject' }))
    fireEvent.change(screen.getByLabelText('Why'), { target: { value: 'Refunds take 30 days, not 3.' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Reject' }).at(-1)!)

    await waitFor(() => expect(api.calls.find((call) => call.path === 'review/5/reject')?.body).toEqual({ reason: 'Refunds take 30 days, not 3.' }))
  })

  it('offers a one-click revert of what agents published under review: auto', async () => {
    const published = revisionFixture({ id: '9', ticket: '0009', status: 'published', replaces: '4' })
    const api = mockApi({
      'GET review': { mode: 'auto', conflicts: [], drafts: [], recent: [published] },
      'POST revisions/4/revert': { outcome: 'published', message: null, revision: null, validation: { ok: true, summary: { pages: 0, errors: 0, warnings: 0 }, issues: [] } },
    })
    renderAdmin(<Review />)

    fireEvent.click(within(await screen.findByRole('article', { name: 'Ticket 0009: Refunds' })).getByRole('button', { name: 'Revert' }))
    await waitFor(() => expect(api.calls.some((call) => call.method === 'POST' && call.path === 'revisions/4/revert')).toBe(true))
  })
})

describe('resolving a docs-migration conflict', () => {
  const detail: RevisionDetail = {
    revision: { ...conflict, conflict: { content_hash: 'sha256:prod' } },
    kind: 'page',
    against: 'conflict',
    proposed: { path: 'support/escalation', title: 'Escalation', frontmatter: { title: 'Escalation' }, body: '# Escalation\n\nThrough PagerDuty.\n', source: '---\ntitle: Escalation\n---\n\n# Escalation\n\nThrough PagerDuty.\n', html: '<p>Through PagerDuty.</p>\n' },
    current: { path: 'support/escalation', title: 'Escalation', frontmatter: { title: 'Escalation' }, body: '# Escalation\n\nThen open an incident.\n', source: '---\ntitle: Escalation\n---\n\n# Escalation\n\nThen open an incident.\n', html: '<p>Then open an incident.</p>\n' },
  }

  it('shows production and the migration side by side, rendered and as source', async () => {
    mockApi({ 'GET review/7': detail })
    renderAdmin(<Ticket id="7" />)

    expect(await screen.findByText('Through PagerDuty.')).toBeTruthy()
    expect(screen.getByText('Then open an incident.')).toBeTruthy()
    expect(screen.getByText('Docs migration')).toBeTruthy()

    fireEvent.click(screen.getByRole('radio', { name: 'Source' }))
    expect(screen.getByRole('figure', { name: 'Source diff from Production to Docs migration' })).toBeTruthy()
  })

  it('keeps production', async () => {
    const api = mockApi({ 'GET review/7': detail, 'POST review/7/reject': { outcome: 'rejected', revision: { ...conflict, status: 'rejected' } } })
    const { navigate } = renderAdmin(<Ticket id="7" />)

    fireEvent.click(await screen.findByRole('button', { name: 'Keep production' }))

    await waitFor(() => expect(api.calls.find((call) => call.path === 'review/7/reject')?.body).toEqual({ reason: 'Kept production.' }))
    await waitFor(() => expect(navigate).toHaveBeenCalledWith({ name: 'review' }))
  })

  it('takes the migration', async () => {
    const api = mockApi({ 'GET review/7': detail, 'POST review/7/publish': { outcome: 'published', message: null, revision: conflict, validation: { ok: true, summary: { pages: 1, errors: 0, warnings: 0 }, issues: [] } } })
    renderAdmin(<Ticket id="7" />)

    fireEvent.click(await screen.findByRole('button', { name: 'Take the migration' }))
    await waitFor(() => expect(api.calls.some((call) => call.path === 'review/7/publish')).toBe(true))
  })

  it('edits a merge and publishes it in place of the conflict', async () => {
    const api = mockApi({
      'GET review/7': detail,
      'POST preview': { title: 'Escalation', description: null, html: '<p>Merged.</p>', toc: [], components: [], issues: [] },
      'POST validate': { ok: true, summary: { pages: 1, errors: 0, warnings: 0 }, issues: [] },
      'POST review/7/edit': { outcome: 'published', message: null, revision: { ...conflict, id: '8', status: 'published' }, validation: { ok: true, summary: { pages: 1, errors: 0, warnings: 0 }, issues: [] } },
    })
    const { notices } = renderAdmin(<Ticket id="7" />)

    fireEvent.click(await screen.findByRole('button', { name: 'Edit a merge' }))
    expect(screen.getByText('Production for reference')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Publish the merge' }))

    await waitFor(() => expect(api.calls.find((call) => call.path === 'review/7/edit')?.body).toEqual({ frontmatter: { title: 'Escalation' }, body: '# Escalation\n\nThrough PagerDuty.\n' }))
    await waitFor(() => expect(notices.push).toHaveBeenCalledWith({ message: 'The merge is live.', tone: 'success' }))
  })
})

describe('the settings screen', () => {
  it('shows why the server refused a change, in its own words', async () => {
    const refusal = 'A database set takes its analytics from config/larecipe.php (sets.kb.integrations), not from larecipe.json. Nothing was written.'
    mockApi({
      'GET manifest': { manifest: { name: 'Knowledge base' }, exists: true, content_hash: 'sha256:aaaa' },
      'PUT manifest': () => ({ status: 422, json: { outcome: 'rejected', message: refusal, revision: null, validation: { ok: false, summary: { pages: 0, errors: 1, warnings: 0 }, issues: [] } } }),
    })
    const { notices } = renderAdmin(<Settings />)

    fireEvent.change(await screen.findByDisplayValue('Knowledge base'), { target: { value: 'KB' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save settings' }))

    await waitFor(() => expect(notices.push).toHaveBeenCalledWith({ message: refusal, tone: 'danger' }))
  })

  it('says where analytics live', async () => {
    mockApi({ 'GET manifest': { manifest: { name: 'Knowledge base' }, exists: true, content_hash: 'sha256:aaaa' } })
    renderAdmin(<Settings />)

    expect(await screen.findByText(/AI and analytics live in config\/larecipe\.php/)).toBeTruthy()
  })
})

describe('the editor workspace', () => {
  it('renders the live preview through the server and draws its issues inline', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const api = mockApi({
      'POST preview': { title: 'Refunds', description: 'Money back.', html: '<p>Money <strong>back</strong>.</p>', toc: [], components: [], issues: [] },
      'POST validate': { ok: false, summary: { pages: 1, errors: 1, warnings: 0 }, issues: [{ level: 'error', rule: 'components.unknown', message: 'Unknown component <Bogus>.', line: 3 }] },
    })

    renderAdmin(<EditorWorkspace path="billing/refunds" frontmatter={{ title: 'Refunds' }} body={'# Refunds\n\n<Bogus />\n'} onFrontmatter={vi.fn()} onBody={vi.fn()} />)

    await act(async () => {
      vi.advanceTimersByTime(PREVIEW_DELAY + 50)
    })

    expect((await screen.findByTestId('preview')).innerHTML).toBe('<p>Money <strong>back</strong>.</p>')
    expect(screen.getByText('Unknown component <Bogus>.')).toBeTruthy()
    expect(screen.getByText('line 3')).toBeTruthy()
    expect(api.calls.find((call) => call.path === 'preview')?.body).toEqual({ version: 'current', locale: 'en', path: 'billing/refunds', frontmatter: { title: 'Refunds' }, body: '# Refunds\n\n<Bogus />\n' })
    expect(document.querySelector('.cm-lintRange-error, .cm-lint-marker-error')).not.toBeNull()
  })

  it('inserts a component from the palette at the cursor', async () => {
    mockApi({ 'POST preview': { title: 'X', description: null, html: '', toc: [], components: [], issues: [] }, 'POST validate': { ok: true, summary: { pages: 1, errors: 0, warnings: 0 }, issues: [] } })
    const onBody = vi.fn()
    renderAdmin(<EditorWorkspace path="x" frontmatter={{}} body="" onFrontmatter={vi.fn()} onBody={onBody} />)

    fireEvent.click(screen.getByRole('radio', { name: 'Components' }))
    fireEvent.click(screen.getByRole('button', { name: /<Callout>/ }))
    fireEvent.change(screen.getByLabelText('title'), { target: { value: 'Heads up' } })
    fireEvent.click(screen.getByRole('button', { name: 'Insert at cursor' }))

    expect(onBody).toHaveBeenLastCalledWith('<Callout title="Heads up">\nWrite the content here.\n</Callout>\n')
  })

  it('edits an Arabic page right to left', () => {
    mockApi({})
    renderAdmin(<EditorWorkspace path="x" frontmatter={{}} body="" onFrontmatter={vi.fn()} onBody={vi.fn()} />, {
      boot: bootFixture({ locales: [{ code: 'ar', label: 'العربية', dir: 'rtl', default: true }] }),
    })

    expect(document.querySelector('.cm-content')?.getAttribute('dir')).toBe('rtl')
    expect(document.querySelector('.lra-preview')?.getAttribute('dir')).toBe('rtl')
  })
})
