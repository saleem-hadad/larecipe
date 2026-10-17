import { expect, test } from '@playwright/test'
import { boxes, layoutShifts, recordLayoutShifts, waitForApp } from './support/helpers'

/**
 * The HTML-first promise: the server paints the whole page, then React takes
 * it over in one commit without moving a single region (ARCHITECTURE.md 7.4,
 * budget CLS < 0.02).
 */
const pages = ['/guide/3.x/installation', '/guide/3.x/components', '/guide/ar/3.x/installation', '/docs/installation']

for (const path of pages) {
  test(`React takes over ${path} without moving anything`, async ({ browser }) => {
    // The page exactly as the server painted it: the app module never runs.
    const serverContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } })
    const server = await serverContext.newPage()
    await server.route(/\/larecipe-assets\/[^/]+\/assets\/app-[^/]+\.js$/, (route) => route.abort())
    await server.goto(path)
    await server.evaluate(() => document.fonts.ready)
    const before = await boxes(server)
    expect(await server.evaluate(() => document.documentElement.classList.contains('lr-ready'))).toBe(false)

    // The same page after React took over.
    const appContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } })
    const app = await appContext.newPage()
    await recordLayoutShifts(app)
    await app.goto(path)
    await waitForApp(app)
    await app.evaluate(() => document.fonts.ready)
    await app.waitForTimeout(400)
    const after = await boxes(app)

    expect(after).toEqual(before)

    const shifts = await layoutShifts(app)
    const cls = shifts.reduce((total, shift) => total + shift.value, 0)
    expect(cls, JSON.stringify(shifts)).toBeLessThan(0.02)

    await serverContext.close()
    await appContext.close()
  })
}

test('React adopts the server article node instead of re-rendering it', async ({ page }) => {
  await page.addInitScript(() => {
    document.addEventListener('DOMContentLoaded', () => {
      ;(window as Window & { __lrArticle?: Element | null }).__lrArticle = document.querySelector('[data-lr-article]')
    })
  })
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const same = await page.evaluate(() => (window as Window & { __lrArticle?: Element | null }).__lrArticle === document.querySelector('[data-lr-article]'))
  expect(same).toBe(true)
})
