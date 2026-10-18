import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

test('the header shows the LaRecipe logo and the docs name', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const brand = page.locator('.lr-header .lr-brand')
  await expect(brand.locator('svg.lr-mark path').first()).toHaveAttribute('fill', '#787AF6')
  await expect(brand.locator('.lr-brand-name')).toHaveText('LaRecipe')
})

test('the switch next to the logo hides the navigation cleanly, widens the article and remembers it', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const toggle = page.getByRole('switch', { name: 'Show the navigation' })
  const sidebar = page.locator('.lr-sidebar')
  const article = page.locator('#lr-article')
  const widthOf = (locator: typeof article) => locator.evaluate((element) => Math.round(element.getBoundingClientRect().width))
  await expect(toggle).toHaveAttribute('aria-checked', 'true')
  await expect(sidebar).toBeVisible()
  const before = await widthOf(article)

  // The sidebar slides out at its full width first, so nothing rewraps while it moves.
  await toggle.click()
  await expect(page.locator('html')).toHaveAttribute('data-lr-sidebar', 'hiding')
  expect(await widthOf(article)).toBe(before)
  expect(await widthOf(sidebar)).toBeGreaterThan(200)

  // Then the layout changes once: the article widens and the choice is remembered.
  await expect(page.locator('html')).toHaveAttribute('data-lr-sidebar', 'hidden')
  await expect(toggle).toHaveAttribute('aria-checked', 'false')
  await expect(sidebar).toBeHidden()
  expect(await widthOf(article)).toBe(880)
  expect(await page.evaluate(() => localStorage.getItem('larecipe:sidebar'))).toBe('hidden')

  // Hidden before first paint on the next load, with no flash of the sidebar.
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('data-lr-sidebar', 'hidden')
  await waitForApp(page)
  await expect(sidebar).toBeHidden()

  await toggle.click()
  await expect(sidebar).toBeVisible()
  await expect(page.locator('html')).not.toHaveAttribute('data-lr-sidebar', /./)
  expect(await widthOf(article)).toBe(before)
  expect(await page.evaluate(() => localStorage.getItem('larecipe:sidebar'))).toBe('shown')
})

test('the sidebar reads as its own panel: bold section titles and a violet tint for the current page', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const sidebar = page.locator('.lr-sidebar')
  const active = sidebar.locator('.lr-nav-link[aria-current="page"]')
  const body = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  expect(await sidebar.evaluate((element) => getComputedStyle(element).backgroundColor)).not.toBe(body)
  await expect(active).toHaveCSS('border-left-width', '0px')
  expect(await active.evaluate((element) => getComputedStyle(element).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)')
  await expect(sidebar.locator('.lr-nav-tab-group > .lr-nav-list > .lr-nav-group > .lr-nav-label').first()).toHaveCSS('font-weight', '700')
})

test('a thin line fills along the top as the reader scrolls', async ({ page }) => {
  await page.goto('/guide/3.x/components')
  await waitForApp(page)

  const bar = page.locator('.lr-header .lr-progress-bar')
  const width = () => bar.evaluate((element) => element.getBoundingClientRect().width)
  expect(await width()).toBeLessThan(2)

  await page.evaluate(() => window.scrollTo(0, (document.documentElement.scrollHeight - window.innerHeight) / 2))
  await expect.poll(width).toBeGreaterThan(400)
})

test('back to top appears well down a page and returns to the title', async ({ page }) => {
  await page.goto('/guide/3.x/components')
  await waitForApp(page)

  const button = page.locator('.lr-back-to-top')
  await expect(button).toBeHidden()

  await page.evaluate(() => window.scrollTo(0, 2000))
  await expect(button).toBeVisible()
  await button.click()
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0)
  await expect(page.locator('.lr-title')).toBeFocused()
  await expect(button).toBeHidden()
})

test('code blocks sit on the slate slab with a soft shadow and no top edge', async ({ page }) => {
  await page.goto('/guide/3.x/components')
  await waitForApp(page)

  for (const block of [page.locator('.lr-prose > figure.lr-code').first(), page.locator('.lr-code-group').first()]) {
    await expect(block).toHaveCSS('border-top-width', '0px')
    await expect(block).toHaveCSS('background-color', 'rgb(52, 66, 88)')
    expect(await block.evaluate((element) => getComputedStyle(element).boxShadow)).not.toBe('none')
  }
  await expect(page.locator('.lr-code-group figure.lr-code').first()).toHaveCSS('box-shadow', 'none')
})
