import { createTranslator, LaRecipeContext, type LaRecipeContextValue } from '@larecipe/ui'
import { fireEvent, render, screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import { afterEach, describe, expect, it } from 'vitest'
import { AppContext, type AppServices } from '../app/context'
import { createRouter } from '../app/router'
import { createStore, initialState } from '../app/store'
import { bootFixture } from '../test/fixtures'
import { CodeGroupIsland } from './CodeGroup'
import { scan } from './Enhancements'
import { codeText } from './shared'
import { TabsIsland } from './Tabs'
import { embedUrl } from './Video'

function Providers({ children }: { children: ReactNode }) {
  const boot = bootFixture()
  const store = createStore(initialState(boot, {}, { preference: 'system', mode: 'light' }))
  const services: AppServices = {
    store,
    router: createRouter({ store, fetch: (async () => new Response('{}')) as never, fullLoad: () => {} }),
    actions: {
      openPalette: () => {},
      closePalette: () => {},
      openAsk: () => {},
      closeAsk: () => {},
      setShortcuts: () => {},
      setSidebar: () => {},
      setTheme: () => {},
      copyText: async () => true,
      copyMarkdown: async () => true,
      copyMcpCommand: async () => true,
      announce: () => {},
    },
  }
  const value: LaRecipeContextValue = {
    boot,
    page: { ...boot.page, version: boot.version, locale: boot.locale.code },
    set: boot.set,
    theme: { mode: 'light', preference: 'system' },
    user: null,
    t: createTranslator(boot.strings),
    locale: boot.locale,
    version: boot.version,
    navigate: () => {},
  }
  return (
    <AppContext.Provider value={services}>
      <LaRecipeContext.Provider value={value}>{children}</LaRecipeContext.Provider>
    </AppContext.Provider>
  )
}

function mount(html: string): HTMLElement {
  const host = document.createElement('div')
  host.innerHTML = html
  document.body.appendChild(host)
  return host
}

afterEach(() => {
  document.body.innerHTML = ''
})

const TABS = `<div data-lr-component="Tabs" data-lr-props='{"labels":["npm","Yarn"]}'><div class="lr-tabs"><section class="lr-tab" data-label="npm"><h4 class="lr-tab-label">npm</h4><div class="lr-tab-body"><p>npm install</p></div></section><section class="lr-tab" data-label="Yarn"><h4 class="lr-tab-label">Yarn</h4><div class="lr-tab-body"><p>yarn add</p></div></section></div></div>`

describe('Tabs', () => {
  it('turns server panels into a tab list, keeping the panels in place', () => {
    const host = mount(TABS)
    const tabs = host.querySelector<HTMLElement>('.lr-tabs')!
    render(
      <Providers>
        <TabsIsland element={tabs} />
      </Providers>,
    )

    const buttons = screen.getAllByRole('tab')
    expect(buttons.map((button) => button.textContent)).toEqual(['npm', 'Yarn'])
    expect(tabs.dataset.enhanced).toBe('')
    const panels = host.querySelectorAll<HTMLElement>('.lr-tab')
    expect(panels[0]!.hidden).toBe(false)
    expect(panels[1]!.hidden).toBe(true)

    fireEvent.click(buttons[1]!)
    expect(panels[0]!.hidden).toBe(true)
    expect(panels[1]!.hidden).toBe(false)
    expect(buttons[1]!.getAttribute('aria-selected')).toBe('true')
  })

  it('moves with the arrow keys', () => {
    const host = mount(TABS)
    render(
      <Providers>
        <TabsIsland element={host.querySelector<HTMLElement>('.lr-tabs')!} />
      </Providers>,
    )
    const buttons = screen.getAllByRole('tab')
    fireEvent.keyDown(buttons[0]!, { key: 'ArrowRight' })
    expect(buttons[1]!.getAttribute('aria-selected')).toBe('true')
    fireEvent.keyDown(buttons[1]!, { key: 'Home' })
    expect(buttons[0]!.getAttribute('aria-selected')).toBe('true')
  })
})

describe('CodeGroup', () => {
  it('shows one block at a time under the server-derived labels', () => {
    const host = mount(
      `<div data-lr-component="CodeGroup" data-lr-props='{"labels":["Composer","Artisan"]}'><div class="lr-code-group"><figure class="lr-code" data-language="bash" data-title="Composer"><figcaption class="lr-code-header"><span class="lr-code-title">Composer</span></figcaption><pre class="lr-pre"><code><span class="lr-line" data-line="1">composer install</span></code></pre></figure><figure class="lr-code" data-language="bash"><figcaption class="lr-code-header"><span class="lr-code-title">bash</span></figcaption><pre class="lr-pre"><code><span class="lr-line" data-line="1">php artisan migrate</span></code></pre></figure></div></div>`,
    )
    const island = host.firstElementChild as HTMLElement
    render(
      <Providers>
        <CodeGroupIsland element={island} />
      </Providers>,
    )

    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Composer', 'Artisan'])
    const figures = host.querySelectorAll<HTMLElement>('figure.lr-code')
    expect(figures[1]!.hidden).toBe(true)
    fireEvent.click(screen.getByRole('tab', { name: 'Artisan' }))
    expect(figures[0]!.hidden).toBe(true)
    expect(figures[1]!.hidden).toBe(false)
    expect(screen.getByRole('button', { name: 'code.copy_label' })).toBeTruthy()
  })
})

describe('copying code', () => {
  it('copies what a reader would type, leaving out removed diff lines', () => {
    const host = mount(
      `<figure class="lr-code"><pre class="lr-pre" data-diff><code><span class="lr-line" data-diff="remove">old()</span>\n<span class="lr-line" data-diff="add">new()</span>\n<span class="lr-line">done()</span></code></pre></figure>`,
    )
    expect(codeText(host.firstElementChild)).toBe('new()\ndone()')
  })
})

describe('scanning an article', () => {
  it('finds every enhanceable element, and code inside groups belongs to the group', () => {
    const host = mount(
      `${TABS}<h2 id="install">Install</h2><figure class="lr-code"><pre class="lr-pre"><code>a</code></pre></figure><pre><code>bare</code></pre><div data-lr-component="CodeGroup"><div class="lr-code-group"><figure class="lr-code"><pre class="lr-pre"><code>b</code></pre></figure></div></div><figure class="lr-frame"><img src="x.png" alt=""></figure><pre class="lr-mermaid"><code>graph TD</code></pre><div class="lr-math" data-display="block"><code>x</code></div>`,
    )
    const found = scan(host)
    expect(found.tabs).toHaveLength(1)
    expect(found.codes).toHaveLength(1)
    expect(found.pres).toHaveLength(1)
    expect(found.groups).toHaveLength(1)
    expect(found.headings.map((heading) => heading.id)).toEqual(['install'])
    expect(found.frames).toHaveLength(1)
    expect(found.mermaids).toHaveLength(1)
    expect(found.maths).toHaveLength(1)
  })
})

describe('video', () => {
  it('builds embed URLs that start playing, and leaves embed URLs intact', () => {
    expect(embedUrl('https://www.youtube.com/watch?v=abc123')).toBe('https://www.youtube-nocookie.com/embed/abc123?autoplay=1')
    expect(embedUrl('https://youtu.be/abc123')).toBe('https://www.youtube-nocookie.com/embed/abc123?autoplay=1')
    expect(embedUrl('https://www.youtube-nocookie.com/embed/abc123')).toBe('https://www.youtube-nocookie.com/embed/abc123?autoplay=1')
    expect(embedUrl('https://player.vimeo.com/video/42')).toBe('https://player.vimeo.com/video/42?autoplay=1')
  })
})
