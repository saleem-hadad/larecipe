import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

test('the theme menu switches light, dark and system, and remembers the choice', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' })
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')

  const menu = page.locator('.lr-header .lr-theme-menu')
  await menu.locator('summary').click()
  await menu.getByRole('button', { name: 'Dark' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await expect(menu).not.toHaveAttribute('open', '')
  expect(await page.evaluate(() => localStorage.getItem('larecipe:theme'))).toBe('dark')

  // Applied before first paint on the next load.
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  const paper = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  expect(paper).toBe('rgb(17, 16, 22)')

  // System follows the operating system.
  await menu.locator('summary').click()
  await menu.getByRole('button', { name: 'System' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
  await page.emulateMedia({ colorScheme: 'dark' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
})

test('without JavaScript the page follows the system theme', async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, colorScheme: 'dark' })
  const page = await context.newPage()
  await page.goto('/guide/3.x/installation')
  const paper = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
  expect(paper).toBe('rgb(17, 16, 22)')
  await context.close()
})
