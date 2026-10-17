import { Fragment, type ReactNode } from 'react'

/**
 * A small, safe Markdown renderer for streamed answers: paragraphs, lists,
 * fenced code, inline code, bold, italic and links. It builds React elements
 * (never HTML strings), so an answer can never inject markup, and it tolerates
 * a half-streamed answer (an unclosed fence renders as code so far).
 */
type Block =
  | { type: 'paragraph'; text: string }
  | { type: 'heading'; text: string }
  | { type: 'code'; language: string; text: string }
  | { type: 'list'; ordered: boolean; items: string[] }

export function parseBlocks(source: string): Block[] {
  const lines = source.replace(/\r\n?/g, '\n').split('\n')
  const blocks: Block[] = []
  let paragraph: string[] = []
  let list: { ordered: boolean; items: string[] } | null = null

  const flushParagraph = () => {
    if (paragraph.length > 0) blocks.push({ type: 'paragraph', text: paragraph.join(' ') })
    paragraph = []
  }
  const flushList = () => {
    if (list) blocks.push({ type: 'list', ...list })
    list = null
  }

  for (let index = 0; index < lines.length; index++) {
    const line = lines[index] ?? ''
    const fence = line.match(/^\s*(```+|~~~+)\s*([\w+-]*)/)

    if (fence) {
      flushParagraph()
      flushList()
      const marker = fence[1] ?? '```'
      const code: string[] = []
      index++
      while (index < lines.length && !(lines[index] ?? '').trim().startsWith(marker)) {
        code.push(lines[index] ?? '')
        index++
      }
      blocks.push({ type: 'code', language: fence[2] ?? '', text: code.join('\n') })
      continue
    }

    const heading = line.match(/^\s*#{1,6}\s+(.*)$/)
    if (heading) {
      flushParagraph()
      flushList()
      blocks.push({ type: 'heading', text: heading[1] ?? '' })
      continue
    }

    const bullet = line.match(/^\s*[-*+]\s+(.*)$/)
    const numbered = line.match(/^\s*\d+[.)]\s+(.*)$/)
    if (bullet || numbered) {
      flushParagraph()
      const ordered = numbered !== null
      if (!list || list.ordered !== ordered) {
        flushList()
        list = { ordered, items: [] }
      }
      list.items.push((bullet?.[1] ?? numbered?.[1] ?? '').trim())
      continue
    }

    if (line.trim() === '') {
      flushParagraph()
      flushList()
      continue
    }

    if (list && /^\s{2,}\S/.test(line)) {
      const items = (list as { items: string[] }).items
      items[items.length - 1] += ` ${line.trim()}`
      continue
    }

    flushList()
    paragraph.push(line.trim())
  }

  flushParagraph()
  flushList()
  return blocks
}

function safeHref(href: string): string | null {
  const trimmed = href.trim()
  if (trimmed.startsWith('/') || trimmed.startsWith('#')) return trimmed
  try {
    const url = new URL(trimmed)
    return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : null
  } catch {
    return null
  }
}

/** Inline Markdown: `code`, **bold**, *italic*, [text](url). */
export function renderInline(text: string, keyPrefix = ''): ReactNode[] {
  const nodes: ReactNode[] = []
  const pattern = /(`[^`]+`)|(\*\*[^*]+\*\*)|(\[[^\]]+\]\([^)\s]+\))|(\*[^*\s][^*]*\*)|(_[^_\s][^_]*_)/g
  let last = 0
  let match: RegExpExecArray | null
  let count = 0

  while ((match = pattern.exec(text)) !== null) {
    if (match.index > last) nodes.push(text.slice(last, match.index))
    const token = match[0]
    const key = `${keyPrefix}${count++}`

    if (token.startsWith('`')) {
      nodes.push(<code key={key}>{token.slice(1, -1)}</code>)
    } else if (token.startsWith('**')) {
      nodes.push(<strong key={key}>{renderInline(token.slice(2, -2), `${key}-`)}</strong>)
    } else if (token.startsWith('[')) {
      const parts = token.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/)
      const href = parts ? safeHref(parts[2] ?? '') : null
      nodes.push(
        href ? (
          <a key={key} href={href} {...(href.startsWith('http') && !href.startsWith(window.location.origin) ? { target: '_blank', rel: 'noopener' } : {})}>
            {parts?.[1]}
          </a>
        ) : (
          <Fragment key={key}>{parts?.[1] ?? token}</Fragment>
        ),
      )
    } else {
      nodes.push(<em key={key}>{renderInline(token.slice(1, -1), `${key}-`)}</em>)
    }
    last = match.index + token.length
  }

  if (last < text.length) nodes.push(text.slice(last))
  return nodes
}

export function Markdown({ source }: { source: string }) {
  return (
    <>
      {parseBlocks(source).map((block, index) => {
        switch (block.type) {
          case 'code':
            return (
              <pre key={index} data-language={block.language || undefined}>
                <code>{block.text}</code>
              </pre>
            )
          case 'heading':
            return (
              <p key={index}>
                <strong>{renderInline(block.text, `${index}-`)}</strong>
              </p>
            )
          case 'list': {
            const items = block.items.map((item, itemIndex) => <li key={itemIndex}>{renderInline(item, `${index}-${itemIndex}-`)}</li>)
            return block.ordered ? <ol key={index}>{items}</ol> : <ul key={index}>{items}</ul>
          }
          default:
            return <p key={index}>{renderInline(block.text, `${index}-`)}</p>
        }
      })}
    </>
  )
}
