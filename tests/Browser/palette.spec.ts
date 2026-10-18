import { expect, test } from '@playwright/test'
import { markWindow, waitForApp, windowMarker } from './support/helpers'

const modifier = process.platform === 'darwin' ? 'Meta' : 'Control'

test.beforeEach(async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
})

test('⌘K searches the client index as you type and opens a page in place', async ({ page }) => {
  await markWindow(page)
  await page.keyboard.press(`${modifier}+k`)

  const dialog = page.getByRole('dialog', { name: 'Search' })
  await expect(dialog).toBeVisible()
  const input = dialog.getByRole('combobox')
  await expect(input).toBeFocused()

  await input.fill('webh')
  const result = dialog.getByRole('option', { name: /Webhooks/ }).first()
  await expect(result).toBeVisible()
  await expect(result.locator('mark').first()).toHaveText(/webh/i)
  await expect(result).toHaveAttribute('aria-selected', 'true')
  await expect(dialog.locator('.lr-palette-group-label', { hasText: 'Pages' })).toBeVisible()
  await expect(dialog.locator('.lr-palette-group-label', { hasText: 'Sections' })).toBeVisible()
  await expect(dialog.locator('.lr-palette-option-path').first()).toHaveText('3.x/guides/webhooks')

  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/\/guide\/3\.x\/guides\/webhooks$/)
  await expect(page.locator('h1.lr-title')).toHaveText('Webhooks')
  await expect(dialog).toBeHidden()
  expect(await windowMarker(page)).toBe('still-here')
})

test('the first row asks AI about the query, and arrows move through results', async ({ page }) => {
  await page.keyboard.press('/')
  const dialog = page.getByRole('dialog', { name: 'Search' })
  await dialog.getByRole('combobox').fill('mcp server')

  const ask = dialog.getByRole('option', { name: /Ask AI about “mcp server”/ })
  await expect(ask).toBeVisible()
  await expect(dialog.getByRole('option').first()).toHaveText(/Ask AI about/)

  await page.keyboard.press('ArrowUp')
  await expect(ask).toHaveAttribute('aria-selected', 'true')
  await page.keyboard.press('Enter')

  const panel = page.getByRole('dialog', { name: 'Ask the docs' })
  await expect(panel).toBeVisible()
  await expect(panel.locator('.lr-ask-question')).toHaveText('mcp server')
})

test('actions switch versions, and Escape closes the palette', async ({ page }) => {
  await page.keyboard.press(`${modifier}+k`)
  const dialog = page.getByRole('dialog', { name: 'Search' })
  await expect(dialog.getByRole('option', { name: /Switch to version 2\.x/ })).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
})

test('a query with no match says so plainly', async ({ page }) => {
  await page.keyboard.press(`${modifier}+k`)
  const dialog = page.getByRole('dialog', { name: 'Search' })
  await dialog.getByRole('combobox').fill('zzqxw')
  await expect(dialog.getByText('No pages match “zzqxw”.')).toBeVisible()
})

test('closing the palette reports the search to the server for analytics', async ({ page }) => {
  await page.keyboard.press(`${modifier}+k`)
  const dialog = page.getByRole('dialog', { name: 'Search' })
  await dialog.getByRole('combobox').fill('zzqxw')
  await expect(dialog.getByText('No pages match “zzqxw”.')).toBeVisible()

  const report = page.waitForRequest((request) => request.url().includes('/_search/report') && request.method() === 'POST')
  // In a search field the first Escape clears the text, the second closes the palette.
  await page.keyboard.press('Escape')
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()

  expect((await report).postDataJSON()).toEqual({ searches: [{ query: 'zzqxw', results: 0 }] })
})
