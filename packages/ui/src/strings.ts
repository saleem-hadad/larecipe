/**
 * UI strings in Laravel's format, so the PHP prerender and React read the same
 * files: ":name" placeholders and "{0} none|{1} one|[2,*] :count many" choices.
 */

export type Replacements = Record<string, string | number>

/** Replace ":name" placeholders. Longer keys first, so ":count" never eats ":countdown". */
export function format(template: string, replace: Replacements = {}): string {
  const keys = Object.keys(replace).sort((a, b) => b.length - a.length)
  let output = template
  for (const key of keys) {
    output = output.split(`:${key}`).join(String(replace[key]))
  }
  return output
}

/**
 * Pick the segment of a choice string for a count. Supports Laravel's explicit
 * forms ("{1}", "[2,10]", "[11,*]"); without ranges, the first segment is
 * singular and the last is plural.
 */
export function choose(template: string, count: number): string {
  const segments = template.split('|')
  if (segments.length === 1) return template

  for (const segment of segments) {
    const exact = segment.match(/^\s*\{(\d+)\}\s?([\s\S]*)$/)
    if (exact && Number(exact[1]) === count) return exact[2] ?? ''

    const range = segment.match(/^\s*\[(\d+|\*),\s*(\d+|\*)\]\s?([\s\S]*)$/)
    if (range) {
      const low = range[1] === '*' ? -Infinity : Number(range[1])
      const high = range[2] === '*' ? Infinity : Number(range[2])
      if (count >= low && count <= high) return range[3] ?? ''
    }
  }

  const plain = segments.filter((segment) => !/^\s*(\{\d+\}|\[(\d+|\*),\s*(\d+|\*)\])/.test(segment))
  if (plain.length === 0) return segments[segments.length - 1] ?? ''
  return (count === 1 ? plain[0] : plain[plain.length - 1]) ?? ''
}

export type Translate = (key: string, replace?: Replacements, count?: number) => string

/** A translator over a flat map of dot keys ("search.label" => "Search the docs"). */
export function createTranslator(strings: Record<string, string> | undefined): Translate {
  const table = strings ?? {}
  return (key, replace = {}, count) => {
    const template = table[key] ?? key
    const chosen = count === undefined ? template : choose(template, count)
    return format(chosen, count === undefined ? replace : { count, ...replace })
  }
}

/**
 * Split a template around its placeholders, so callers can put elements (a
 * <time>, a mono hash) where ":time" or ":name" sit.
 */
export function splitTemplate(template: string, keys: string[]): Array<string | { key: string }> {
  if (keys.length === 0) return [template]
  const pattern = new RegExp(`:(${[...keys].sort((a, b) => b.length - a.length).join('|')})`, 'g')
  const parts: Array<string | { key: string }> = []
  let last = 0
  for (const match of template.matchAll(pattern)) {
    if (match.index > last) parts.push(template.slice(last, match.index))
    parts.push({ key: match[1] ?? '' })
    last = match.index + match[0].length
  }
  if (last < template.length) parts.push(template.slice(last))
  return parts
}
