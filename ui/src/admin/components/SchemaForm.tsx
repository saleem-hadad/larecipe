import { Button, Field, Input, Textarea } from '@larecipe/ui'
import { useEffect, useId, useMemo, useState, type ReactNode } from 'react'
import { parse as parseYaml, stringify as stringifyYaml } from 'yaml'
import { emptyValue, labelFor, resolve, typeOf, validate, variantFor, variantsOf, type SchemaError } from '../lib/schema'
import type { JsonSchema } from '../types'
import { AdminIcon } from './AdminIcon'
import { Segmented } from './ui'

/**
 * Forms generated from LaRecipe's JSON Schemas (frontmatter, larecipe.json),
 * so a new key in a schema shows up in the admin without UI work. Every form
 * has a raw toggle (YAML for frontmatter, JSON for the manifest) for whatever
 * a form cannot say, and both sides are checked against the same schema.
 */

type Value = unknown
type Errors = Map<string, string>

const LONG_TEXT = new Set(['description', 'instructions'])

export function errorsByPath(errors: SchemaError[]): Errors {
  const map: Errors = new Map()
  for (const error of errors) {
    if (!map.has(error.path)) map.set(error.path, error.message)
  }
  return map
}

export function SchemaEditor({
  schema,
  value,
  onChange,
  format,
  label,
  dir = 'ltr',
  primary,
}: {
  schema: JsonSchema
  value: Record<string, unknown>
  onChange: (value: Record<string, unknown>) => void
  format: 'yaml' | 'json'
  label: string
  dir?: 'ltr' | 'rtl'
  /** Keys shown up front; the others wait under "More settings" unless they are set. */
  primary?: string[]
}) {
  const [mode, setMode] = useState<'form' | 'raw'>('form')
  const errors = useMemo(() => errorsByPath(validate(value, schema)), [value, schema])

  return (
    <div className="lra-schema-editor">
      <div className="lra-schema-toolbar">
        <Segmented
          label={`${label}: form or raw ${format.toUpperCase()}`}
          size="sm"
          value={mode}
          onChange={setMode}
          options={[
            { value: 'form', label: 'Form' },
            { value: 'raw', label: format === 'yaml' ? 'YAML' : 'JSON' },
          ]}
        />
        {errors.size > 0 && (
          <span className="lra-schema-count" role="status">
            {errors.size} problem{errors.size === 1 ? '' : 's'}
          </span>
        )}
      </div>
      {mode === 'form' ? (
        <SchemaObject schema={schema} root={schema} value={value} onChange={(next) => onChange(next as Record<string, unknown>)} path="" errors={errors} depth={0} dir={dir} primary={primary} />
      ) : (
        <RawEditor value={value} onChange={onChange} format={format} schema={schema} label={label} />
      )}
    </div>
  )
}

function RawEditor({ value, onChange, format, schema, label }: { value: Record<string, unknown>; onChange: (value: Record<string, unknown>) => void; format: 'yaml' | 'json'; schema: JsonSchema; label: string }) {
  const serialize = (data: Record<string, unknown>) => (format === 'yaml' ? (Object.keys(data).length === 0 ? '' : stringifyYaml(data, { lineWidth: 0 })) : JSON.stringify(data, null, 2))
  const [text, setText] = useState(() => serialize(value))
  const [problem, setProblem] = useState<string | null>(null)
  const id = useId()

  useEffect(() => {
    // Follow outside changes (a reset, a reload) unless the text already means the same thing.
    try {
      const parsed = format === 'yaml' ? (parseYaml(text) ?? {}) : JSON.parse(text || '{}')
      if (JSON.stringify(parsed) === JSON.stringify(value)) return
    } catch {
      // The person is mid-edit; keep their text.
      return
    }
    setText(serialize(value))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value])

  const change = (next: string) => {
    setText(next)
    try {
      const parsed = format === 'yaml' ? (parseYaml(next) ?? {}) : JSON.parse(next || '{}')
      if (typeOf(parsed) !== 'object') {
        setProblem(`The ${format.toUpperCase()} must be a map of keys and values.`)
        return
      }
      setProblem(null)
      onChange(parsed as Record<string, unknown>)
    } catch (error) {
      setProblem(error instanceof Error ? error.message.split('\n')[0] ?? 'This does not parse.' : 'This does not parse.')
    }
  }

  const schemaErrors = problem ? [] : validate(value, schema)

  return (
    <div className="lra-raw">
      <label className="lr-sr" htmlFor={id}>
        {label} as {format.toUpperCase()}
      </label>
      <textarea id={id} className="lr-textarea lra-raw-text" spellCheck={false} dir="ltr" value={text} onChange={(event) => change(event.target.value)} rows={Math.min(24, Math.max(6, text.split('\n').length + 1))} aria-invalid={problem ? true : undefined} />
      {problem && (
        <p className="lr-field-error" role="alert">
          {problem}
        </p>
      )}
      {schemaErrors.length > 0 && (
        <ul className="lra-raw-errors">
          {schemaErrors.slice(0, 8).map((error) => (
            <li key={error.path + error.message}>
              <code>{error.path || '(root)'}</code> {error.message}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

interface FieldArgs {
  schema: JsonSchema
  root: JsonSchema
  value: Value
  onChange: (value: Value) => void
  path: string
  errors: Errors
  depth: number
  dir: 'ltr' | 'rtl'
}

const childPath = (path: string, key: string | number) => (path === '' ? String(key) : `${path}/${key}`)

function SchemaObject({ schema, root, value, onChange, path, errors, depth, dir, primary }: FieldArgs & { primary?: string[] }) {
  const node = resolve(schema, root)
  const object = (typeOf(value) === 'object' ? value : {}) as Record<string, unknown>
  const properties = Object.entries(node.properties ?? {}).filter(([key]) => key !== '$schema')
  const known = new Set(properties.map(([key]) => key).concat('$schema'))
  const others = Object.keys(object).filter((key) => !known.has(key))

  if (properties.length === 0) {
    if (node.additionalProperties && typeof node.additionalProperties === 'object') {
      return <MapField schema={node} root={root} value={object} onChange={onChange} path={path} errors={errors} depth={depth} dir={dir} />
    }
    return <JsonField value={object} onChange={onChange} path={path} errors={errors} />
  }

  const set = (key: string, next: Value) => {
    const copy = { ...object }
    if (next === undefined) delete copy[key]
    else copy[key] = next
    onChange(copy)
  }

  const field = ([key, property]: [string, JsonSchema]) => (
    <SchemaField key={key} name={key} required={(node.required ?? []).includes(key)} schema={property} root={root} value={object[key]} onChange={(next) => set(key, next)} path={childPath(path, key)} errors={errors} depth={depth} dir={dir} />
  )
  const upFront = primary ? properties.filter(([key]) => primary.includes(key) || object[key] !== undefined) : properties
  const later = primary ? properties.filter(([key]) => !upFront.some(([shown]) => shown === key)) : []

  return (
    <div className="lra-fields" data-depth={depth}>
      {upFront.map(field)}
      {later.length > 0 && (
        <details className="lra-more">
          <summary className="lra-fieldset-summary">
            <span>More settings</span>
            <span className="lra-fieldset-hint">{later.map(([key]) => labelFor(key)).join(', ')}</span>
          </summary>
          <div className="lra-fields" data-depth={depth}>
            {later.map(field)}
          </div>
        </details>
      )}
      {others.length > 0 && (
        <p className="lra-field-note">
          Also set here: {others.map((key) => <code key={key}>{key}</code>).reduce<ReactNode[]>((list, item, index) => (index === 0 ? [item] : [...list, ', ', item]), [])}. Edit them in the raw view.
        </p>
      )}
    </div>
  )
}

function SchemaField({ name, required, bare = false, ...args }: FieldArgs & { name: string; required: boolean; bare?: boolean }) {
  const { schema, root, value, onChange, path, errors, depth, dir } = args
  const node = resolve(schema, root)
  const variants = variantsOf(node, root)
  const label = labelFor(name)
  const hint = node.description
  const error = errors.get(path)

  if (variants.length > 1) {
    const current = variantFor(value, variants, root)

    return (
      <div className="lra-field-variants" role="group" aria-label={label}>
        <div className="lra-variant-head">
          <span className="lr-field-label">{label}</span>
          {value !== undefined ? (
            <Segmented label={`${label}: shape`} size="sm" value={current?.key ?? '0'} onChange={(key) => onChange(emptyValue(variants.find((variant) => variant.key === key)!.schema, root))} options={variants.map((variant) => ({ value: variant.key, label: variant.label }))} />
          ) : (
            <Button variant="ghost" size="sm" onClick={() => onChange(emptyValue(variants[0]!.schema, root))} icon={<AdminIcon name="Plus" size={14} />}>
              Set
            </Button>
          )}
          {value !== undefined && !required && (
            <button type="button" className="lra-link-button" onClick={() => onChange(undefined)}>
              Clear
            </button>
          )}
        </div>
        {hint && <p className="lr-field-hint">{hint}</p>}
        {value !== undefined && current && <SchemaField {...args} name={name} required={required} bare schema={{ ...current.schema, description: undefined }} />}
        {error && <p className="lr-field-error">{error}</p>}
      </div>
    )
  }

  const type = Array.isArray(node.type) ? node.type[0] : node.type

  if (node.const !== undefined) {
    return <p className="lra-field-note">{label}: {JSON.stringify(node.const)}</p>
  }

  if (node.enum) {
    return (
      <Field label={label} hint={hint} error={error} hideLabel={bare}>
        <select className="lr-input lra-select" value={value === undefined ? '' : JSON.stringify(value)} onChange={(event) => onChange(event.target.value === '' ? undefined : JSON.parse(event.target.value))}>
          {!required && <option value="">Not set</option>}
          {node.enum.map((option) => (
            <option key={JSON.stringify(option)} value={JSON.stringify(option)}>
              {String(option)}
            </option>
          ))}
        </select>
      </Field>
    )
  }

  if (type === 'boolean') {
    // Not set, yes or no: some flags default to true (provenance, skillMd), so "off" must be sayable.
    return (
      <Field label={label} hint={hint} error={error} hideLabel={bare}>
        <select className="lr-input lra-select" value={value === true ? 'yes' : value === false ? 'no' : ''} onChange={(event) => onChange(event.target.value === 'yes' ? true : event.target.value === 'no' ? false : undefined)}>
          {!required && <option value="">Not set</option>}
          <option value="yes">Yes</option>
          <option value="no">No</option>
        </select>
      </Field>
    )
  }

  if (type === 'number' || type === 'integer') {
    return (
      <Field label={label} hint={hint} error={error} hideLabel={bare}>
        <Input type="number" inputMode="numeric" dir="ltr" value={typeof value === 'number' ? value : ''} onChange={(event) => onChange(event.target.value === '' ? undefined : Number(event.target.value))} />
      </Field>
    )
  }

  if (type === 'array') {
    const items = resolve(node.items, root)
    const list = Array.isArray(value) ? value : []
    if ((items.type === 'string' || items.type === undefined) && !items.oneOf && !items.properties) {
      return <StringList label={label} hint={hint} error={error} value={list as string[]} onChange={(next) => onChange(next.length === 0 && !required ? undefined : next)} dir={dir} />
    }
    return <ObjectList {...args} label={label} hint={hint} list={list} items={node.items ?? {}} />
  }

  if (type === 'object') {
    const content = <SchemaObject {...args} depth={depth + 1} value={value ?? {}} onChange={(next) => onChange(typeOf(next) === 'object' && Object.keys(next as object).length === 0 && !required ? undefined : next)} />

    if (bare) return content

    return depth === 0 ? (
      <details className="lra-fieldset" open={name === 'theme' || value !== undefined}>
        <summary className="lra-fieldset-summary">
          <span>{label}</span>
          {hint && <span className="lra-fieldset-hint">{hint}</span>}
        </summary>
        {content}
        {error && <p className="lr-field-error">{error}</p>}
      </details>
    ) : (
      <fieldset className="lra-subfields">
        <legend className="lr-field-label">{label}</legend>
        {content}
        {error && <p className="lr-field-error">{error}</p>}
      </fieldset>
    )
  }

  const long = LONG_TEXT.has(name)
  const text = typeof value === 'string' || typeof value === 'number' ? String(value) : ''
  const control = long ? (
    <Textarea dir="auto" rows={2} value={text} onChange={(event) => onChange(event.target.value === '' && !required ? undefined : event.target.value)} />
  ) : (
    <Input dir={name === 'title' || name === 'label' || name === 'name' ? dir : 'ltr'} value={text} required={required} onChange={(event) => onChange(event.target.value === '' && !required ? undefined : event.target.value)} />
  )

  return (
    <Field label={required && !bare ? `${label} (required)` : label} hint={hint} error={error} hideLabel={bare}>
      {control}
    </Field>
  )
}

function StringList({ label, hint, error, value, onChange, dir }: { label: string; hint?: string; error?: string; value: string[]; onChange: (value: string[]) => void; dir: 'ltr' | 'rtl' }) {
  const [draft, setDraft] = useState('')
  const id = useId()
  const add = () => {
    const item = draft.trim()
    if (item === '') return
    onChange([...value, item])
    setDraft('')
  }

  return (
    <div className="lr-field lra-string-list">
      <label className="lr-field-label" htmlFor={id}>
        {label}
      </label>
      {value.length > 0 && (
        <ul className="lra-chips" aria-label={label}>
          {value.map((item, index) => (
            <li key={`${item}-${index}`} className="lra-chip">
              <code dir="auto">{item}</code>
              <button type="button" className="lra-chip-remove" aria-label={`Remove ${item}`} onClick={() => onChange(value.filter((_, at) => at !== index))}>
                <AdminIcon name="X" size={12} />
              </button>
            </li>
          ))}
        </ul>
      )}
      <div className="lra-inline">
        <Input
          id={id}
          dir={dir === 'rtl' ? 'auto' : 'ltr'}
          value={draft}
          placeholder="Add, then press Enter"
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              event.preventDefault()
              add()
            }
          }}
        />
        <Button variant="outline" size="sm" onClick={add} disabled={draft.trim() === ''}>
          Add
        </Button>
      </div>
      {hint && <p className="lr-field-hint">{hint}</p>}
      {error && <p className="lr-field-error">{error}</p>}
    </div>
  )
}

function ObjectList({ label, hint, list, items, root, onChange, path, errors, depth, dir }: FieldArgs & { label: string; hint?: string; list: unknown[]; items: JsonSchema }) {
  const resolved = resolve(items, root)

  return (
    <fieldset className="lra-object-list">
      <legend className="lr-field-label">{label}</legend>
      {hint && <p className="lr-field-hint">{hint}</p>}
      {list.length === 0 && <p className="lra-muted">None yet.</p>}
      <ol className="lra-object-items">
        {list.map((item, index) => (
          <li key={index} className="lra-object-item">
            <div className="lra-object-item-head">
              <span className="lr-meta">#{index + 1}</span>
              <span className="lra-grow" />
              <button type="button" className="lra-icon-button" aria-label={`Move ${label} ${index + 1} up`} disabled={index === 0} onClick={() => onChange(swap(list, index, index - 1))}>
                <AdminIcon name="ArrowUp" size={14} />
              </button>
              <button type="button" className="lra-icon-button" aria-label={`Move ${label} ${index + 1} down`} disabled={index === list.length - 1} onClick={() => onChange(swap(list, index, index + 1))}>
                <AdminIcon name="ArrowDown" size={14} />
              </button>
              <button type="button" className="lra-icon-button" aria-label={`Remove ${label} ${index + 1}`} onClick={() => onChange(list.filter((_, at) => at !== index))}>
                <AdminIcon name="Trash" size={14} />
              </button>
            </div>
            <SchemaField name={`${labelFor(label)} ${index + 1}`} required schema={items} root={root} value={item} onChange={(next) => onChange(list.map((current, at) => (at === index ? next : current)))} path={childPath(path, index)} errors={errors} depth={depth + 1} dir={dir} />
          </li>
        ))}
      </ol>
      <Button variant="outline" size="sm" icon={<AdminIcon name="Plus" size={14} />} onClick={() => onChange([...list, emptyValue(resolved.oneOf ? (variantsOf(resolved, root).find((variant) => variant.label === 'Fields')?.schema ?? resolved) : resolved, root)])}>
        Add
      </Button>
    </fieldset>
  )
}

function swap(list: unknown[], from: number, to: number): unknown[] {
  const copy = [...list]
  ;[copy[from], copy[to]] = [copy[to], copy[from]]
  return copy
}

function MapField({ schema, root, value, onChange, path, errors, depth, dir }: FieldArgs & { value: Record<string, unknown> }) {
  const valueSchema = schema.additionalProperties as JsonSchema
  const keys = resolve(schema.propertyNames, root).enum as string[] | undefined
  const entries = Object.entries(value)
  const unused = keys?.filter((key) => !(key in value)) ?? []
  const [newKey, setNewKey] = useState('')
  const id = useId()

  const add = () => {
    const key = ((keys ? newKey || unused[0] : newKey) ?? '').trim()
    if (!key || key in value) return
    onChange({ ...value, [key]: emptyValue(valueSchema, root) })
    setNewKey('')
  }

  return (
    <div className="lra-map">
      {entries.map(([key, item]) => (
        <div key={key} className="lra-map-row">
          <code className="lra-map-key">{key}</code>
          <div className="lra-grow">
            <SchemaField name={key} required schema={valueSchema} root={root} value={item} onChange={(next) => onChange({ ...value, [key]: next })} path={childPath(path, key)} errors={errors} depth={depth + 1} dir={dir} />
          </div>
          <button
            type="button"
            className="lra-icon-button"
            aria-label={`Remove ${key}`}
            onClick={() => {
              const copy = { ...value }
              delete copy[key]
              onChange(copy)
            }}
          >
            <AdminIcon name="Trash" size={14} />
          </button>
        </div>
      ))}
      {(!keys || unused.length > 0) && (
        <div className="lra-inline">
          <label className="lr-sr" htmlFor={id}>
            New key
          </label>
          {keys ? (
            <select id={id} className="lr-input lra-select" value={newKey || unused[0]} onChange={(event) => setNewKey(event.target.value)}>
              {unused.map((key) => (
                <option key={key} value={key}>
                  {key}
                </option>
              ))}
            </select>
          ) : (
            <Input id={id} dir="ltr" value={newKey} placeholder="key" onChange={(event) => setNewKey(event.target.value)} />
          )}
          <Button variant="outline" size="sm" onClick={add} icon={<AdminIcon name="Plus" size={14} />}>
            Add
          </Button>
        </div>
      )}
    </div>
  )
}

function JsonField({ value, onChange, path, errors }: { value: Record<string, unknown>; onChange: (value: Value) => void; path: string; errors: Errors }) {
  const [text, setText] = useState(() => JSON.stringify(value, null, 2))
  const [problem, setProblem] = useState<string | null>(null)

  return (
    <Field label="JSON" hideLabel error={problem ?? errors.get(path)}>
      <Textarea
        dir="ltr"
        className="lra-raw-text"
        rows={4}
        value={text}
        onChange={(event) => {
          setText(event.target.value)
          try {
            onChange(JSON.parse(event.target.value || '{}'))
            setProblem(null)
          } catch {
            setProblem('This is not valid JSON yet.')
          }
        }}
      />
    </Field>
  )
}
