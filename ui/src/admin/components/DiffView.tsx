import { useMemo, useState } from 'react'
import { diffLines, diffStats, withContext } from '../lib/diff'

/**
 * The source diff: what a pull request would show. Changed lines are marked
 * in the gutter (+ in the success hue, - in the danger hue), never washed;
 * long unchanged stretches fold away and can be opened.
 */
export function SourceDiff({ before, after, beforeLabel, afterLabel }: { before: string; after: string; beforeLabel: string; afterLabel: string }) {
  const diff = useMemo(() => diffLines(before, after), [before, after])
  const stats = diffStats(diff)
  const [expanded, setExpanded] = useState(false)
  const chunks = useMemo(() => (expanded ? [{ type: 'lines' as const, lines: diff }] : withContext(diff)), [diff, expanded])

  return (
    <figure className="lra-diff" aria-label={`Source diff from ${beforeLabel} to ${afterLabel}`}>
      <figcaption className="lra-diff-head">
        <span className="lr-label">
          {beforeLabel} <span aria-hidden="true">→</span> {afterLabel}
        </span>
        <span className="lra-diff-stats lr-mono">
          <span data-tone="add">+{stats.additions}</span> <span data-tone="remove">−{stats.deletions}</span>
        </span>
      </figcaption>
      {stats.additions + stats.deletions === 0 ? (
        <p className="lra-diff-same">No differences in the source.</p>
      ) : (
        <div className="lra-diff-body" dir="ltr">
          <table className="lra-diff-table">
            <tbody>
              {chunks.map((chunk, index) =>
                chunk.type === 'gap' ? (
                  <tr key={index} className="lra-diff-gap">
                    <td colSpan={4}>
                      <button type="button" className="lra-link-button" onClick={() => setExpanded(true)}>
                        {chunk.count} unchanged line{chunk.count === 1 ? '' : 's'}
                      </button>
                    </td>
                  </tr>
                ) : (
                  chunk.lines.map((line, row) => (
                    <tr key={`${index}-${row}`} className="lra-diff-line" data-type={line.type}>
                      <td className="lra-diff-number">{line.oldLine ?? ''}</td>
                      <td className="lra-diff-number">{line.newLine ?? ''}</td>
                      <td className="lra-diff-marker" aria-label={line.type === 'add' ? 'added' : line.type === 'remove' ? 'removed' : undefined}>
                        {line.type === 'add' ? '+' : line.type === 'remove' ? '-' : ''}
                      </td>
                      <td className="lra-diff-text">{line.text || ' '}</td>
                    </tr>
                  ))
                ),
              )}
            </tbody>
          </table>
        </div>
      )}
    </figure>
  )
}

/** Both sides rendered by the server renderer, as readers would see them, side by side. */
export function RenderedDiff({ before, after, beforeLabel, afterLabel, dir }: { before: string | null; after: string | null; beforeLabel: string; afterLabel: string; dir: 'ltr' | 'rtl' }) {
  return (
    <div className="lra-rendered-diff">
      {[
        { label: beforeLabel, html: before, side: 'before' },
        { label: afterLabel, html: after, side: 'after' },
      ].map((side) => (
        <section key={side.side} className="lra-rendered-side" aria-label={side.label} data-side={side.side}>
          <p className="lr-label">{side.label}</p>
          {side.html === null ? (
            <p className="lra-muted">Nothing here: {side.side === 'before' ? 'the page does not exist yet.' : 'this change removes the page.'}</p>
          ) : (
            <article className="lr-article lr-prose lra-preview-article" dir={dir} dangerouslySetInnerHTML={{ __html: side.html }} />
          )}
        </section>
      ))}
    </div>
  )
}
