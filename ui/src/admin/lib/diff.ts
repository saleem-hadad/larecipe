/**
 * Line diffs for the review queue and revision history: what a pull request
 * would show for the same change. A longest-common-subsequence diff is plenty
 * for docs pages (hundreds of lines), and its output is stable and readable.
 */

export interface DiffLine {
  type: 'same' | 'add' | 'remove'
  text: string
  /** 1-based line in the old text (same and remove). */
  oldLine: number | null
  /** 1-based line in the new text (same and add). */
  newLine: number | null
}

export interface DiffStats {
  additions: number
  deletions: number
}

function lines(text: string): string[] {
  if (text === '') return []
  const split = text.replace(/\r\n/g, '\n').split('\n')
  if (split[split.length - 1] === '') split.pop()
  return split
}

export function diffLines(before: string, after: string): DiffLine[] {
  const a = lines(before)
  const b = lines(after)
  const n = a.length
  const m = b.length

  // Trim the common prefix and suffix first: most edits touch a few lines.
  let start = 0
  while (start < n && start < m && a[start] === b[start]) start++
  let endA = n
  let endB = m
  while (endA > start && endB > start && a[endA - 1] === b[endB - 1]) {
    endA--
    endB--
  }

  const midA = a.slice(start, endA)
  const midB = b.slice(start, endB)
  const rows = midA.length
  const cols = midB.length
  const table: Uint32Array[] = Array.from({ length: rows + 1 }, () => new Uint32Array(cols + 1))

  for (let i = rows - 1; i >= 0; i--) {
    for (let j = cols - 1; j >= 0; j--) {
      table[i]![j] = midA[i] === midB[j] ? table[i + 1]![j + 1]! + 1 : Math.max(table[i + 1]![j]!, table[i]![j + 1]!)
    }
  }

  const result: DiffLine[] = []
  for (let k = 0; k < start; k++) result.push({ type: 'same', text: a[k]!, oldLine: k + 1, newLine: k + 1 })

  let i = 0
  let j = 0
  while (i < rows && j < cols) {
    if (midA[i] === midB[j]) {
      result.push({ type: 'same', text: midA[i]!, oldLine: start + i + 1, newLine: start + j + 1 })
      i++
      j++
    } else if (table[i + 1]![j]! >= table[i]![j + 1]!) {
      result.push({ type: 'remove', text: midA[i]!, oldLine: start + i + 1, newLine: null })
      i++
    } else {
      result.push({ type: 'add', text: midB[j]!, oldLine: null, newLine: start + j + 1 })
      j++
    }
  }
  while (i < rows) {
    result.push({ type: 'remove', text: midA[i]!, oldLine: start + i + 1, newLine: null })
    i++
  }
  while (j < cols) {
    result.push({ type: 'add', text: midB[j]!, oldLine: null, newLine: start + j + 1 })
    j++
  }

  for (let k = 0; k < n - endA; k++) {
    result.push({ type: 'same', text: a[endA + k]!, oldLine: endA + k + 1, newLine: endB + k + 1 })
  }

  return result
}

export function diffStats(diff: DiffLine[]): DiffStats {
  return {
    additions: diff.filter((line) => line.type === 'add').length,
    deletions: diff.filter((line) => line.type === 'remove').length,
  }
}

export type DiffChunk = { type: 'lines'; lines: DiffLine[] } | { type: 'gap'; count: number }

/** Changed lines with `context` unchanged lines around them; long unchanged runs fold into a gap. */
export function withContext(diff: DiffLine[], context = 3): DiffChunk[] {
  const keep = diff.map(() => false)
  diff.forEach((line, index) => {
    if (line.type === 'same') return
    for (let k = Math.max(0, index - context); k <= Math.min(diff.length - 1, index + context); k++) keep[k] = true
  })

  const chunks: DiffChunk[] = []
  let gap = 0
  let current: DiffLine[] = []

  diff.forEach((line, index) => {
    if (keep[index]) {
      if (gap > 0) {
        chunks.push({ type: 'gap', count: gap })
        gap = 0
      }
      current.push(line)
    } else {
      if (current.length > 0) {
        chunks.push({ type: 'lines', lines: current })
        current = []
      }
      gap++
    }
  })

  if (current.length > 0) chunks.push({ type: 'lines', lines: current })
  if (gap > 0) chunks.push({ type: 'gap', count: gap })

  return chunks
}
