import { expect, test } from '@playwright/test'
import { markWindow, waitForApp, windowMarker } from './support/helpers'

test('docs links swap the article in place and keep the head true', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await markWindow(page)

  const request = page.waitForRequest((candidate) => candidate.url().endsWith('/guide/3.x/components') && (candidate.headers().accept ?? '').includes('application/vnd.larecipe.page+json'))
  await page.locator('.lr-sidebar .lr-nav-link', { hasText: 'Components' }).click()
  await request

  await expect(page).toHaveURL(/\/guide\/3\.x\/components$/)
  await expect(page.locator('h1.lr-title')).toHaveText('Components')
  await expect(page).toHaveTitle('Components · LaRecipe')
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/guide\/3\.x\/components$/)
  await expect(page.locator('link[rel="alternate"][type="text/markdown"]')).toHaveAttribute('href', /components\.md$/)
  await expect(page.locator('.lr-sidebar .lr-nav-link[aria-current="page"] .lr-nav-text')).toHaveText('Components')
  await expect(page.locator('.lr-toc-link').first()).toHaveText('Callouts')
  await expect(page.locator('.lr-ticket')).toContainText('Codex documented the Video placeholder')
  expect(await windowMarker(page)).toBe('still-here')

  // Focus moves to the new title, for screen reader and keyboard users.
  await expect(page.locator('h1.lr-title')).toBeFocused()

  // Islands mount on the new article.
  await expect(page.locator('.lr-tab-trigger').first()).toBeVisible()

  // Back restores the previous page, still without a reload.
  await page.goBack()
  await expect(page.locator('h1.lr-title')).toHaveText('Installation')
  await expect(page).toHaveTitle('Installation · LaRecipe')
  expect(await windowMarker(page)).toBe('still-here')
})

test('links prefetch on hover intent', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  // Webhooks is not a neighbour of Installation, so nothing fetched it on idle.
  const prefetch = page.waitForRequest((candidate) => candidate.url().endsWith('/guide/3.x/guides/webhooks') && (candidate.headers().accept ?? '').includes('application/vnd.larecipe.page+json'))
  await page.locator('.lr-sidebar .lr-nav-link', { hasText: 'Webhooks' }).hover()
  await prefetch
})

test('a new UI build forces a full page load', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await markWindow(page)

  await page.route('**/guide/3.x/guides/webhooks', async (route) => {
    if (!(route.request().headers().accept ?? '').includes('application/vnd.larecipe.page+json')) return route.continue()
    const response = await route.fetch()
    const json = await response.json()
    await route.fulfill({ response, json: { ...json, build: 'a-newer-build' }, headers: { ...response.headers(), 'x-larecipe-build': 'a-newer-build' } })
  })

  await page.locator('.lr-sidebar .lr-nav-link', { hasText: 'Webhooks' }).click()
  await expect(page.locator('h1.lr-title')).toHaveText('Webhooks')
  expect(await windowMarker(page)).toBeUndefined()
})

test('switching language is a full load into the other direction', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  await page.locator('.lr-header .lr-locale-menu > summary').click()
  await page.locator('.lr-header .lr-locale-menu .lr-menu-item[lang="ar"]').click()
  await expect(page).toHaveURL(/\/guide\/ar\/3\.x\/installation$/)
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.locator('h1.lr-title')).toHaveText('التثبيت')
})

test('in-page anchors scroll without leaving the page', async ({ page }) => {
  await page.goto('/guide/3.x/guides/long-page')
  await waitForApp(page)
  await markWindow(page)

  await page.locator('.lr-toc-link', { hasText: 'Validate' }).click()
  await expect(page).toHaveURL(/#validate$/)
  await expect(page.locator('#validate')).toBeInViewport()
  await expect(page.locator('.lr-toc-link', { hasText: 'Validate' })).toHaveAttribute('data-active', '')
  expect(await windowMarker(page)).toBe('still-here')
})
