import { Button, Field, Input } from '@larecipe/ui'
import { useMemo, useState } from 'react'
import { AdminIcon } from '../components/AdminIcon'
import type { ComponentInfo } from '../types'
import { componentSnippet, initialProps } from './snippets'

/**
 * The component palette: every component the server will render (built-ins,
 * live references, gate blocks and the host's own), from the same registry
 * the renderer uses. Pick one, fill its props, insert it at the cursor.
 */
export function ComponentPalette({ components, onInsert }: { components: ComponentInfo[]; onInsert: (snippet: string) => void }) {
  const [query, setQuery] = useState('')
  const [selected, setSelected] = useState<ComponentInfo | null>(null)
  const [props, setProps] = useState<Record<string, unknown>>({})

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase()
    return components.filter((component) => needle === '' || component.name.toLowerCase().includes(needle) || (component.description ?? '').toLowerCase().includes(needle))
  }, [components, query])

  const choose = (component: ComponentInfo) => {
    setSelected(component)
    setProps(initialProps(component))
  }

  if (selected) {
    const entries = Object.entries(selected.props)

    return (
      <div className="lra-palette" aria-label={`Insert ${selected.name}`}>
        <div className="lra-palette-head">
          <button type="button" className="lra-link-button" onClick={() => setSelected(null)}>
            <AdminIcon name="ArrowLeft" size={14} /> All components
          </button>
        </div>
        <p className="lra-palette-name">
          <code>&lt;{selected.name}&gt;</code>
        </p>
        {selected.description && <p className="lra-muted">{selected.description}</p>}
        <form
          className="lra-fields"
          onSubmit={(event) => {
            event.preventDefault()
            onInsert(componentSnippet(selected, props))
            setSelected(null)
          }}
        >
          {entries.length === 0 && <p className="lra-muted">No props. Its content goes between the tags.</p>}
          {entries.map(([name, spec]) => {
            const label = `${name}${spec.required ? ' (required)' : ''}`
            const value = props[name]

            if (spec.enum) {
              return (
                <Field key={name} label={label} hint={spec.description}>
                  <select className="lr-input lra-select" value={value === undefined ? '' : String(value)} onChange={(event) => setProps({ ...props, [name]: event.target.value === '' ? undefined : event.target.value })}>
                    {!spec.required && <option value="">Default{spec.default !== undefined ? ` (${String(spec.default)})` : ''}</option>}
                    {spec.enum.map((option) => (
                      <option key={option} value={option}>
                        {option}
                      </option>
                    ))}
                  </select>
                </Field>
              )
            }

            if (spec.type === 'boolean') {
              return (
                <label key={name} className="lra-check-label">
                  <input type="checkbox" checked={value === true} onChange={(event) => setProps({ ...props, [name]: event.target.checked || undefined })} />
                  <span>{name}</span>
                </label>
              )
            }

            return (
              <Field key={name} label={label} hint={spec.description}>
                <Input
                  dir="auto"
                  type={spec.type === 'integer' || spec.type === 'number' ? 'number' : 'text'}
                  value={value === undefined ? '' : String(value)}
                  placeholder={spec.default !== undefined ? String(spec.default) : undefined}
                  onChange={(event) => setProps({ ...props, [name]: event.target.value === '' ? undefined : spec.type === 'integer' || spec.type === 'number' ? Number(event.target.value) : event.target.value })}
                />
              </Field>
            )
          })}
          <pre className="lra-snippet" dir="ltr">
            <code>{componentSnippet(selected, props)}</code>
          </pre>
          <Button type="submit" variant="secondary" size="sm" icon={<AdminIcon name="Plus" size={14} />}>
            Insert at cursor
          </Button>
        </form>
      </div>
    )
  }

  return (
    <div className="lra-palette" aria-label="Components">
      <Field label="Find a component" hideLabel>
        <Input type="search" placeholder="Find a component" value={query} onChange={(event) => setQuery(event.target.value)} />
      </Field>
      <ul className="lra-palette-list">
        {filtered.map((component) => (
          <li key={component.name}>
            <button type="button" className="lra-palette-item" onClick={() => choose(component)}>
              <code className="lra-palette-tag">&lt;{component.name}&gt;</code>
              <span className="lra-palette-description">{component.description ?? (component.origin === 'host' ? 'A component from this app.' : '')}</span>
            </button>
          </li>
        ))}
        {filtered.length === 0 && <li className="lra-muted">No component matches "{query}".</li>}
      </ul>
    </div>
  )
}
