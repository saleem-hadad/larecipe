import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

test.use({ permissions: ['clipboard-read', 'clipboard-write'] })

test('the page header carries the breadcrumb, provenance and the fresh dot', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  await expect(page.locator('.lr-breadcrumb')).toHaveText('Get started/Installation')
  await expect(page.locator('.lr-provenance .lr-fresh-dot')).toHaveCount(1)
  await expect(page.locator('.lr-provenance time')).toHaveText('2 hours ago')

  // Fresh dots mark pages changed in the last seven days, and only those.
  const fresh = page.locator('.lr-sidebar .lr-nav-link:has(.lr-fresh-dot) .lr-nav-text')
  await expect(fresh).toHaveText(['Introduction', 'Installation', 'Components', 'Connect your agents'])
})

test('a page without recent changes has no fresh dot and no ticket without provenance', async ({ page }) => {
  await page.goto('/guide/3.x/guides/frontmatter')
  await waitForApp(page)
  await expect(page.locator('.lr-provenance .lr-fresh-dot')).toHaveCount(0)
  await expect(page.locator('.lr-provenance')).toContainText('Updated 1 week ago')
  await expect(page.locator('.lr-ticket')).toHaveCount(0)
})

test('the Last change ticket shows the change behind the page', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const ticket = page.locator('.lr-ticket')
  await expect(ticket.locator('.lr-ticket-head')).toContainText('Last change#0142')
  await expect(ticket.locator('.lr-ticket-meta')).toContainText('9f3a2c1 · 2 pages · +14 −3')
  await expect(ticket.locator('.lr-ticket-meta')).toContainText('Approved by @saleem')
  await expect(ticket.getByRole('link', { name: 'View change' })).toHaveAttribute('href', 'https://github.com/saleem-hadad/larecipe/commit/9f3a2c1')
})

test('For agents: copy as Markdown, open in tools, connect MCP', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const actions = page.locator('.lr-rail .lr-page-actions')
  await expect(actions.locator('.lr-action')).toHaveCount(6)

  await actions.getByRole('button', { name: 'Copy as Markdown' }).click()
  await expect(actions.getByRole('button', { name: 'Copied' })).toBeVisible()
  const markdown = await page.evaluate(() => navigator.clipboard.readText())
  expect(markdown).toContain('title: Installation')

  await expect(actions.getByRole('link', { name: 'Open in Claude' })).toHaveAttribute('href', /^https:\/\/claude\.ai\/new\?q=Read%20http.*installation\.md/)
  await expect(actions.getByRole('link', { name: 'Open in ChatGPT' })).toHaveAttribute('target', '_blank')

  await actions.getByRole('button', { name: 'Connect MCP' }).click()
  const command = await page.evaluate(() => navigator.clipboard.readText())
  expect(command).toMatch(/^claude mcp add --transport http larecipe http:\/\/127\.0\.0\.1:\d+\/guide\/mcp$/)
})

test('page feedback posts the vote and an optional comment once', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  // Analytics integrations listen for the same detail as a DOM event.
  await page.evaluate(() => {
    document.addEventListener('larecipe:feedback', (event) => {
      ;(window as Window & { __lrFeedback?: unknown }).__lrFeedback = (event as CustomEvent).detail
    })
  })

  const feedback = page.locator('.lr-feedback')
  await feedback.getByRole('button', { name: 'Yes' }).click()
  await feedback.getByRole('textbox').fill('Clear steps.')
  const request = page.waitForRequest((candidate) => candidate.url().endsWith('/guide/_feedback') && candidate.method() === 'POST')
  await feedback.getByRole('button', { name: 'Send' }).click()
  const expected = { path: 'installation', version: '3.x', locale: 'en', helpful: true, comment: 'Clear steps.' }
  expect((await request).postDataJSON()).toEqual(expected)
  expect(await page.evaluate(() => (window as Window & { __lrFeedback?: unknown }).__lrFeedback)).toEqual(expected)
  await expect(page.locator('.lr-feedback')).toContainText('Thanks. Your feedback was sent.')
})

test('? lists the keyboard shortcuts', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await page.keyboard.press('Shift+?')
  const dialog = page.getByRole('dialog', { name: 'Keyboard shortcuts' })
  await expect(dialog).toBeVisible()
  await expect(dialog).toContainText('Ask the docs')
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
})

test('on phones the navigation is a sheet and page tools sit under the title', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  await expect(page.locator('.lr-rail')).toBeHidden()
  await expect(page.locator('.lr-page-tools .lr-split-main')).toBeVisible()

  const sheet = page.locator('#lr-sidebar')
  await expect(sheet).toBeHidden()
  await page.getByRole('button', { name: 'Open navigation' }).click()
  await expect(sheet).toBeVisible()
  await expect(page.getByRole('button', { name: 'Close navigation' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(sheet).toBeHidden()

  await page.getByRole('button', { name: 'Open navigation' }).click()
  await sheet.locator('.lr-nav-link', { hasText: /^Components/ }).click()
  await expect(page.locator('h1.lr-title')).toHaveText('Components')
  await expect(sheet).toBeHidden()

  await page.locator('.lr-toc-disclosure > summary').click()
  await expect(page.locator('.lr-toc-disclosure .lr-menu-item').first()).toHaveText('Callouts')
})

test('a missing page is a designed 404', async ({ page }) => {
  const response = await page.goto('/guide/3.x/nothing-here')
  expect(response?.status()).toBe(404)
  await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible()
  await expect(page.locator('.lr-not-found-body code')).toHaveText('/guide/3.x/nothing-here')
  await expect(page.getByRole('link', { name: 'Back to LaRecipe' })).toBeVisible()
})

test('Markdown lists keep their markers', async ({ page }) => {
  await page.goto('/guide/3.x')
  await waitForApp(page)

  const list = page.locator('.lr-prose ul:not([class])').first()
  await expect(list).toHaveCSS('list-style-type', 'disc')
})
