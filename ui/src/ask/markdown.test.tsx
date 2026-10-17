import { render } from '@testing-library/react'
import type { UIMessage } from 'ai'
import { describe, expect, it } from 'vitest'
import { citationsOf } from './AskPanel'
import { Markdown, parseBlocks } from './markdown'

describe('the answer renderer', () => {
  it('parses paragraphs, lists and code, even half streamed', () => {
    expect(parseBlocks('Run this:\n\n```bash\nphp artisan')).toEqual([
      { type: 'paragraph', text: 'Run this:' },
      { type: 'code', language: 'bash', text: 'php artisan' },
    ])
    expect(parseBlocks('1. One\n2. Two')).toEqual([{ type: 'list', ordered: true, items: ['One', 'Two'] }])
  })

  it('never renders HTML from the answer, and only safe links', () => {
    const { container } = render(<Markdown source={'<img src=x onerror=alert(1)> **bold** [ok](/guide/3.x) [bad](javascript:alert(1))'} />)
    expect(container.querySelector('img')).toBeNull()
    expect(container.textContent).toContain('<img src=x onerror=alert(1)>')
    expect(container.querySelector('strong')?.textContent).toBe('bold')
    const links = Array.from(container.querySelectorAll('a'))
    expect(links.map((link) => link.getAttribute('href'))).toEqual(['/guide/3.x'])
  })
})

describe('citations', () => {
  it('keeps only this site’s docs pages, once each', () => {
    const message = {
      id: 'm1',
      role: 'assistant',
      parts: [
        { type: 'text', text: 'Answer' },
        { type: 'source-url', sourceId: 's1', url: '/guide/3.x/agents#mcp', title: 'Connect your agents' },
        { type: 'source-url', sourceId: 's2', url: '/guide/3.x/agents#mcp', title: 'Again' },
        { type: 'source-url', sourceId: 's3', url: 'https://evil.example.com/guide/3.x', title: 'Elsewhere' },
        { type: 'source-url', sourceId: 's4', url: '/admin/secret', title: 'Not docs' },
      ],
    } as unknown as UIMessage

    expect(citationsOf(message, '/guide')).toEqual([{ url: '/guide/3.x/agents#mcp', title: 'Connect your agents', path: '3.x/agents#mcp' }])
  })
})
