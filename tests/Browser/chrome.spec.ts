import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

test('the header shows the LaRecipe logo and the docs name', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const brand = page.locator('.lr-header .lr-brand')
  await expect(brand.locator('svg.lr-mark path').first()).toHaveAttribute('fill', '#787AF6')
  await expect(brand.locator('.lr-brand-name')).toHaveText('LaRecipe')
})

test('the switch next to the logo hides the navigation and remembers it', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const toggle = page.getByRole('switch', { name: 'Show the navigation' })
  const sidebar = page.locator('.lr-sidebar')
  await expect(toggle).toHaveAttribute('aria-checked', 'true')
  await expect(sidebar).toBeVisible()

  await toggle.click()
  await expect(toggle).toHaveAttribute('aria-checked', 'false')
  await expect(sidebar).toBeHidden()
  const left = await page.locator('.lr-page-header').evaluate((element) => element.getBoundingClientRect().left)
  expect(left).toBeLessThan(120)
  expect(await page.evaluate(() => localStorage.getItem('larecipe:sidebar'))).toBe('hidden')

  // Hidden before first paint on the next load, with no flash of the sidebar.
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('data-lr-sidebar', 'hidden')
  await waitForApp(page)
  await expect(sidebar).toBeHidden()

  await toggle.click()
  await expect(sidebar).toBeVisible()
  expect(await page.evaluate(() => localStorage.getItem('larecipe:sidebar'))).toBe('shown')
})

test('the current page in the navigation has a 2px violet edge', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const active = page.locator('.lr-sidebar .lr-nav-link[aria-current="page"]')
  await expect(active).toHaveCSS('border-left-width', '2px')
  await expect(active).toHaveCSS('border-left-style', 'solid')
  expect(await active.evaluate((element) => getComputedStyle(element).borderLeftColor)).not.toBe('rgba(0, 0, 0, 0)')
  expect(await page.locator('.lr-sidebar').evaluate((element) => getComputedStyle(element).backgroundColor)).not.toBe(
    await page.evaluate(() => getComputedStyle(document.body).backgroundColor),
  )
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

test('code blocks wear a thin violet top edge', async ({ page }) => {
  await page.goto('/guide/3.x/components')
  await waitForApp(page)

  await expect(page.locator('.lr-prose > figure.lr-code').first()).toHaveCSS('border-top-width', '2px')
  await expect(page.locator('.lr-code-group').first()).toHaveCSS('border-top-width', '2px')
  await expect(page.locator('.lr-code-group figure.lr-code').first()).toHaveCSS('border-top-width', '0px')
})
