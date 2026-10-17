import { createTranslator, defineExtension, installRuntime, LaRecipeContext, registry, useLaRecipe, type LaRecipeContextValue } from '@larecipe/ui'
import { act, render, screen } from '@testing-library/react'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { bootFixture } from '../test/fixtures'
import { findIslands, islandProps, IslandsHost } from './Islands'

function context(): LaRecipeContextValue {
  const boot = bootFixture()
  return {
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
}

function article(html: string): HTMLElement {
  const element = document.createElement('article')
  element.innerHTML = html
  document.body.appendChild(element)
  return element
}

beforeAll(() => installRuntime('test'))
afterEach(() => {
  registry.reset()
  document.body.innerHTML = ''
})

describe('island props', () => {
  it('reads JSON props and survives broken JSON', () => {
    const good = article(`<div data-lr-component="Subscribe" data-lr-props='{"list":"news"}'></div>`).firstElementChild as HTMLElement
    const bad = article(`<div data-lr-component="Subscribe" data-lr-props='{oops'></div>`).firstElementChild as HTMLElement
    expect(islandProps(good)).toEqual({ list: 'news' })
    expect(islandProps(bad)).toEqual({})
  })

  it('finds only islands a registered component takes over, outermost first', () => {
    const root = article(
      `<div data-lr-component="Outer" data-lr-props='{}'><div data-lr-component="Inner" data-lr-props='{}'></div></div><div data-lr-component="Unknown"></div>`,
    )
    const components = new Map([
      ['Outer', () => null],
      ['Inner', () => null],
    ])
    expect(findIslands(root, components).map((island) => island.name)).toEqual(['Outer'])
  })
})

describe('IslandsHost', () => {
  it('mounts a host component into its placeholder with its props and the page context', async () => {
    const root = article(`<p>Intro</p><div data-lr-component="Greeting" data-lr-props='{"name":"Ada"}'><form>fallback</form></div>`)
    function Greeting({ name }: { name?: unknown }) {
      const { page } = useLaRecipe()
      return (
        <p data-testid="greeting">
          Hello {String(name)} on {page.title}
        </p>
      )
    }
    defineExtension({ components: { Greeting: Greeting as never } })

    render(
      <LaRecipeContext.Provider value={context()}>
        <IslandsHost root={root} />
      </LaRecipeContext.Provider>,
    )

    expect(await screen.findByTestId('greeting')).toHaveProperty('textContent', 'Hello Ada on Installation')
    expect(root.querySelector('form')).toBeNull()
  })

  it('keeps the server fallback until an extension registers late', async () => {
    const root = article(`<div data-lr-component="Late" data-lr-props='{}'><form>fallback</form></div>`)
    render(
      <LaRecipeContext.Provider value={context()}>
        <IslandsHost root={root} />
      </LaRecipeContext.Provider>,
    )
    expect(root.querySelector('form')).not.toBeNull()

    act(() => {
      defineExtension({ components: { Late: (() => <span data-testid="late">ready</span>) as never } })
    })
    expect(await screen.findByTestId('late')).toBeTruthy()
  })

  it('falls back to the server HTML when a component throws', async () => {
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {})
    const root = article(`<div data-lr-component="Broken" data-lr-props='{}'><p>server fallback</p></div>`)
    defineExtension({
      components: {
        Broken: (() => {
          throw new Error('boom')
        }) as never,
      },
    })

    render(
      <LaRecipeContext.Provider value={context()}>
        <IslandsHost root={root} />
      </LaRecipeContext.Provider>,
    )

    expect(await screen.findByText('server fallback')).toBeTruthy()
    errors.mockRestore()
  })
})
