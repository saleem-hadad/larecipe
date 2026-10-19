import { act, fireEvent, render, screen, within } from '@testing-library/react'
import { useState } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import frontmatter from '../../../../resources/schemas/frontmatter.schema.json'
import manifest from '../../../../resources/schemas/larecipe.schema.json'
import type { JsonSchema } from '../types'
import { RevisionTicket } from './RevisionTicket'
import { SchemaEditor } from './SchemaForm'
import { RenderedDiff, SourceDiff } from './DiffView'
import { revisionFixture } from '../test/harness'

afterEach(() => {
  document.body.innerHTML = ''
})

function Editor({ schema, initial, format, onValue }: { schema: JsonSchema; initial: Record<string, unknown>; format: 'yaml' | 'json'; onValue: (value: Record<string, unknown>) => void }) {
  const [value, setValue] = useState(initial)
  return (
    <SchemaEditor
      schema={schema}
      value={value}
      format={format}
      label="Frontmatter"
      primary={['title', 'description', 'sources']}
      onChange={(next) => {
        setValue(next)
        onValue(next)
      }}
    />
  )
}

describe('forms generated from the JSON Schemas', () => {
  it('draws the frontmatter fields from the schema and writes what a person types', () => {
    const onValue = vi.fn()
    render(<Editor schema={frontmatter as JsonSchema} initial={{ title: 'Refunds' }} format="yaml" onValue={onValue} />)

    const title = screen.getByLabelText('Title') as HTMLInputElement
    expect(title.value).toBe('Refunds')
    expect(screen.getByText('Required unless the first # heading gives it.')).toBeTruthy()

    fireEvent.change(screen.getByLabelText('Description'), { target: { value: 'Getting money back.' } })
    expect(onValue).toHaveBeenLastCalledWith({ title: 'Refunds', description: 'Getting money back.' })

    // Clearing a field removes the key instead of writing an empty string.
    fireEvent.change(title, { target: { value: '' } })
    expect(onValue).toHaveBeenLastCalledWith({ description: 'Getting money back.' })
  })

  it('keeps the less common settings under "More settings" until they are set', () => {
    render(<Editor schema={frontmatter as JsonSchema} initial={{ title: 'Refunds', hidden: true }} format="yaml" onValue={vi.fn()} />)

    const more = screen.getByText('More settings').closest('details')!
    expect(within(more).queryByLabelText('Hidden')).toBeNull()
    expect((screen.getByLabelText('Hidden') as HTMLSelectElement).value).toBe('yes')
    expect(within(more).getByLabelText('Layout')).toBeTruthy()
  })

  it('offers each shape a field may take: one source glob or a list', () => {
    const onValue = vi.fn()
    render(<Editor schema={frontmatter as JsonSchema} initial={{}} format="yaml" onValue={onValue} />)

    const sources = screen.getByRole('group', { name: 'Sources' })
    fireEvent.click(within(sources).getByRole('button', { name: 'Set' }))
    expect(onValue).toHaveBeenLastCalledWith({ sources: '' })

    fireEvent.click(within(sources).getByRole('radio', { name: 'List' }))
    expect(onValue).toHaveBeenLastCalledWith({ sources: [] })

    const input = within(sources).getByPlaceholderText('Add, then press Enter')
    fireEvent.change(input, { target: { value: 'app/Billing/**' } })
    fireEvent.keyDown(input, { key: 'Enter' })
    expect(onValue).toHaveBeenLastCalledWith({ sources: ['app/Billing/**'] })
  })

  it('switches to raw YAML and back, parsing what was typed', () => {
    const onValue = vi.fn()
    render(<Editor schema={frontmatter as JsonSchema} initial={{ title: 'Refunds' }} format="yaml" onValue={onValue} />)

    fireEvent.click(screen.getByRole('radio', { name: 'YAML' }))
    const raw = screen.getByLabelText('Frontmatter as YAML') as HTMLTextAreaElement
    expect(raw.value).toBe('title: Refunds\n')

    fireEvent.change(raw, { target: { value: 'title: Refunds\nlayout: wide\nsources:\n  - app/Billing/**\n' } })
    expect(onValue).toHaveBeenLastCalledWith({ title: 'Refunds', layout: 'wide', sources: ['app/Billing/**'] })

    fireEvent.change(raw, { target: { value: 'title: [unclosed' } })
    expect(screen.getByRole('alert').textContent).not.toBe('')

    fireEvent.click(screen.getByRole('radio', { name: 'Form' }))
    expect((screen.getByLabelText('Layout') as HTMLSelectElement).value).toBe('"wide"')
  })

  it('says what is wrong next to the field, against the schema', () => {
    render(<Editor schema={manifest as JsonSchema} initial={{ name: 'KB', theme: { radius: 'round' } }} format="json" onValue={vi.fn()} />)

    expect(screen.getByRole('status').textContent).toBe('1 problem')
    expect(screen.getByText('Must be one of: sharp, soft.')).toBeTruthy()
  })

  it('edits lists of objects: versions in larecipe.json', () => {
    const onValue = vi.fn()
    render(<Editor schema={manifest as JsonSchema} initial={{ name: 'KB', versions: [{ id: '2.x' }] }} format="json" onValue={onValue} />)

    const versions = screen.getByRole('group', { name: 'Versions' })
    fireEvent.click(within(versions).getByRole('button', { name: 'Add' }))
    expect(onValue).toHaveBeenLastCalledWith({ name: 'KB', versions: [{ id: '2.x' }, {}] })

    fireEvent.click(within(versions).getByRole('button', { name: 'Remove Versions 1' }))
    expect(onValue).toHaveBeenLastCalledWith({ name: 'KB', versions: [{}] })
  })
})

describe('tickets and diffs', () => {
  it('prints a revision as a ticket: number, provenance, status and the decisions', () => {
    render(<RevisionTicket revision={revisionFixture()} kind="draft" print actions={<button type="button">Publish</button>} />)

    const ticket = screen.getByRole('article', { name: 'Ticket 0005: Refunds' })
    expect(ticket.dataset.print).toBe('true')
    expect(ticket.textContent).toContain('Say how long refunds take')
    expect(ticket.textContent).toContain('claude-code · remote MCP · write · billing/refunds')
    expect(within(ticket).getByText('draft')).toBeTruthy()
    expect(within(ticket).getByRole('button', { name: 'Publish' })).toBeTruthy()
  })

  it('shows the source diff with markers in the gutter', () => {
    const { container } = render(<SourceDiff before={'# A\n\nOld.\n'} after={'# A\n\nNew.\n'} beforeLabel="Production" afterLabel="Draft" />)

    expect(container.querySelector('[data-type="remove"] .lra-diff-text')?.textContent).toBe('Old.')
    expect(container.querySelector('[data-type="add"] .lra-diff-text')?.textContent).toBe('New.')
    expect(screen.getByText('+1')).toBeTruthy()
  })

  it('renders both sides as readers would see them, right to left for an Arabic page', () => {
    const { container } = render(<RenderedDiff before="<p>قديم</p>" after={null} beforeLabel="Production" afterLabel="Draft" dir="rtl" />)

    expect(container.querySelector('article')?.getAttribute('dir')).toBe('rtl')
    expect(screen.getByText('Nothing here: this change removes the page.')).toBeTruthy()
  })
})

describe('the print motion', () => {
  it('is a data attribute the stylesheet animates, and nothing else', async () => {
    const { rerender } = render(<RevisionTicket revision={revisionFixture()} kind="draft" />)
    expect(screen.getByRole('article').dataset.print).toBeUndefined()

    await act(async () => rerender(<RevisionTicket revision={revisionFixture()} kind="draft" print />))
    expect(screen.getByRole('article').dataset.print).toBe('true')
  })
})
