import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

test('the theme button switches between light and dark in one click, and remembers the choice', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' })
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')

  const toggle = page.locator('.lr-header .lr-theme-toggle')
  await expect(toggle).toHaveAttribute('aria-pressed', 'false')
  await expect(toggle.locator('.lr-theme-to-dark')).toBeVisible()
  await toggle.click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await expect(toggle).toHaveAttribute('aria-pressed', 'true')
  await expect(toggle.locator('.lr-theme-to-light')).toBeVisible()
  expect(await page.evaluate(() => localStorage.getItem('larecipe:theme'))).toBe('dark')

  // Applied before first paint on the next load.
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  const paper = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  expect(paper).toBe('rgb(17, 16, 22)')

  await toggle.click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
})

test('with no choice made, the page follows the system theme', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' })
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await page.emulateMedia({ colorScheme: 'light' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
})

test('without JavaScript the page follows the system theme', async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, colorScheme: 'dark' })
  const page = await context.newPage()
  await page.goto('/guide/3.x/installation')
  const paper = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  expect(paper).toBe('rgb(17, 16, 22)')
  await context.close()
})
