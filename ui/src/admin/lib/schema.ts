import type { JsonSchema } from '../types'

/**
 * Just enough JSON Schema for LaRecipe's own three schemas (frontmatter,
 * larecipe.json, navigation.json): the forms are generated from them and
 * checked against them before anything is sent, so a person learns about a
 * mistake next to the field instead of from a refused save.
 *
 * Supported: type (and type lists), enum, const, required, properties,
 * additionalProperties, propertyNames, items, oneOf, if/then/else, minLength,
 * pattern and local $refs. That is every keyword the shipped schemas use.
 */

export interface SchemaError {
  /** JSON-pointer-like path, "" for the root: "theme/radius", "versions/1/id". */
  path: string
  message: string
}

export function resolve(schema: JsonSchema | undefined, root: JsonSchema): JsonSchema {
  let current = schema ?? {}
  let guard = 0

  while (current.$ref && guard++ < 20) {
    const name = current.$ref.replace(/^#\/\$defs\//, '')
    current = root.$defs?.[name] ?? {}
  }

  return current
}

export function typeOf(value: unknown): string {
  if (value === null) return 'null'
  if (Array.isArray(value)) return 'array'
  if (typeof value === 'number') return Number.isInteger(value) ? 'integer' : 'number'
  return typeof value
}

function matchesType(value: unknown, type: string | string[] | undefined): boolean {
  if (type === undefined) return true
  const actual = typeOf(value)
  const types = Array.isArray(type) ? type : [type]
  return types.some((expected) => expected === actual || (expected === 'number' && actual === 'integer'))
}

const join = (path: string, key: string | number) => (path === '' ? String(key) : `${path}/${key}`)

export function validate(value: unknown, schema: JsonSchema, root: JsonSchema = schema, path = ''): SchemaError[] {
  const node = resolve(schema, root)
  const errors: SchemaError[] = []

  if (node.if) {
    const branch = validate(value, node.if, root, path).length === 0 ? node.then : node.else
    if (branch) errors.push(...validate(value, branch, root, path))
  }

  if (node.oneOf) {
    const matches = node.oneOf.filter((option) => validate(value, option, root, path).length === 0)
    if (matches.length === 0) {
      const sameType = node.oneOf.filter((option) => matchesType(value, resolve(option, root).type))
      if (sameType.length === 1) return validate(value, sameType[0]!, root, path)
      errors.push({ path, message: 'This value does not have one of the accepted shapes.' })
      return errors
    }
  }

  if (!matchesType(value, node.type)) {
    const expected = Array.isArray(node.type) ? node.type.join(' or ') : node.type
    errors.push({ path, message: `Expected ${expected === 'array' ? 'a list' : expected === 'object' ? 'a group of fields' : `a ${expected}`}.` })
    return errors
  }

  if (node.const !== undefined && JSON.stringify(value) !== JSON.stringify(node.const)) {
    errors.push({ path, message: `Must be ${JSON.stringify(node.const)}.` })
  }

  if (node.enum && !node.enum.some((option) => JSON.stringify(option) === JSON.stringify(value))) {
    errors.push({ path, message: `Must be one of: ${node.enum.map((option) => String(option)).join(', ')}.` })
  }

  if (typeof value === 'string') {
    if (node.minLength !== undefined && value.length < node.minLength) {
      errors.push({ path, message: node.minLength === 1 ? 'Must not be empty.' : `Must be at least ${node.minLength} characters.` })
    }
    if (node.pattern && !new RegExp(node.pattern, 'u').test(value)) {
      errors.push({ path, message: `Does not match the expected format (${node.pattern}).` })
    }
  }

  if (Array.isArray(value) && node.items) {
    value.forEach((item, index) => errors.push(...validate(item, node.items!, root, join(path, index))))
  }

  if (typeOf(value) === 'object') {
    const object = value as Record<string, unknown>

    for (const key of node.required ?? []) {
      if (!(key in object)) errors.push({ path: join(path, key), message: 'Required.' })
    }

    for (const [key, child] of Object.entries(object)) {
      const property = node.properties?.[key]

      if (node.propertyNames) {
        errors.push(...validate(key, node.propertyNames, root, join(path, key)).map((error) => ({ ...error, message: `The key "${key}" is not allowed. ${error.message}` })))
      }

      if (property) {
        errors.push(...validate(child, property, root, join(path, key)))
      } else if (node.additionalProperties === false && key !== '$schema') {
        errors.push({ path: join(path, key), message: `"${key}" is not a known setting here.` })
      } else if (typeof node.additionalProperties === 'object') {
        errors.push(...validate(child, node.additionalProperties, root, join(path, key)))
      }
    }
  }

  return errors
}

/** "redirect_from" and "docsPath" become "Redirect from" and "Docs path". */
export function labelFor(key: string): string {
  const words = key
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/[_-]+/g, ' ')
    .trim()
    .toLowerCase()

  const fixed = words.replace(/\bllms txt\b/, 'llms.txt').replace(/\bmd\b/, 'MD').replace(/\bcta\b/, 'call to action').replace(/\bga4\b/, 'GA4')
  return fixed.startsWith('llms.txt') ? fixed : fixed.charAt(0).toUpperCase() + fixed.slice(1)
}

export type Variant = { key: string; label: string; schema: JsonSchema }

/**
 * The shapes a field may take (a oneOf, or a list of types): a source glob
 * may be one string or a list, a logo one URL or one per mode, inserts off or
 * a map. The form offers them as a small choice.
 */
export function variantsOf(schema: JsonSchema, root: JsonSchema): Variant[] {
  const node = resolve(schema, root)
  const options: JsonSchema[] = node.oneOf
    ? node.oneOf.map((option) => resolve(option, root))
    : Array.isArray(node.type) && node.type.length > 1
      ? node.type.map((type) => ({ ...node, type }))
      : []

  return options.map((option, index) => ({ key: String(index), label: variantLabel(option), schema: option }))
}

function variantLabel(schema: JsonSchema): string {
  if (schema.const === false) return 'Off'
  if (schema.const !== undefined) return String(schema.const)
  switch (Array.isArray(schema.type) ? schema.type[0] : schema.type) {
    case 'string':
      return 'Text'
    case 'number':
    case 'integer':
      return 'Number'
    case 'boolean':
      return 'Yes or no'
    case 'array':
      return 'List'
    case 'object':
      return 'Fields'
    default:
      return 'Value'
  }
}

/** Which variant a value currently fits (the first one that validates, else the first of its type). */
export function variantFor(value: unknown, variants: Variant[], root: JsonSchema): Variant | undefined {
  if (value === undefined) return variants[0]
  return variants.find((variant) => validate(value, variant.schema, root).length === 0) ?? variants.find((variant) => matchesType(value, variant.schema.type)) ?? variants[0]
}

/** A fresh value for a schema: what a field starts with when someone switches to it. */
export function emptyValue(schema: JsonSchema, root: JsonSchema): unknown {
  const node = resolve(schema, root)
  if (node.const !== undefined) return node.const
  if (node.enum) return node.enum[0]
  switch (Array.isArray(node.type) ? node.type[0] : node.type) {
    case 'array':
      return []
    case 'object':
      return {}
    case 'boolean':
      return false
    case 'number':
    case 'integer':
      return 0
    default:
      return ''
  }
}

/** Drop empty strings, empty lists and empty groups, so a form never writes noise into a file. */
export function prune(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map(prune).filter((item) => item !== undefined)
  }

  if (typeOf(value) === 'object') {
    const result: Record<string, unknown> = {}
    for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
      const pruned = prune(child)
      const empty = pruned === undefined || pruned === '' || (Array.isArray(pruned) && pruned.length === 0) || (typeOf(pruned) === 'object' && Object.keys(pruned as object).length === 0)
      if (!empty) result[key] = pruned
    }
    return result
  }

  return value
}
