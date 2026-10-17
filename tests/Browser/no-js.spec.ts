import { expect, test } from '@playwright/test'

/** Everything reads with JavaScript disabled: the HTML-first rule. */
test.use({ javaScriptEnabled: false })

test('a page is complete and readable without JavaScript', async ({ page }) => {
  await page.goto('/guide/3.x/installation')

  await expect(page.locator('h1.lr-title')).toHaveText('Installation')
  await expect(page.locator('.lr-lead')).toContainText('Add LaRecipe to any Laravel app')
  await expect(page.locator('.lr-provenance')).toContainText('Updated 2 hours ago by Claude Code')
  await expect(page.locator('#lr-article')).toContainText('composer require larecipe/larecipe')
  await expect(page.locator('.lr-sidebar .lr-nav-link[aria-current="page"] .lr-nav-text')).toHaveText('Installation')
  await expect(page.locator('.lr-toc-link').first()).toHaveText('Requirements')
  await expect(page.locator('.lr-ticket')).toContainText('Claude Code updated Connect your agents')

  // Every tab panel and every code block of a group is readable.
  await expect(page.locator('.lr-tab-body')).toHaveCount(2)
  await expect(page.locator('.lr-tab-body').nth(1)).toBeVisible()
  await expect(page.locator('[data-lr-component="CodeGroup"] figure.lr-code')).toHaveCount(3)
  await expect(page.locator('[data-lr-component="CodeGroup"] figure.lr-code').nth(2)).toBeVisible()

  // Controls that need JavaScript are not shown at all.
  await expect(page.locator('.lr-search-trigger')).toBeHidden()
  await expect(page.locator('.lr-ask-button')).toBeHidden()
  await expect(page.locator('.lr-theme-menu')).toBeHidden()
  await expect(page.locator('.lr-feedback')).toBeHidden()

  // Agent destinations are plain links.
  await expect(page.locator('.lr-rail a.lr-action', { hasText: 'View as Markdown' })).toHaveAttribute('href', /installation\.md$/)
})

test('menus open without JavaScript', async ({ page }) => {
  await page.goto('/guide/3.x/installation')

  await page.locator('.lr-version-menu > summary').click()
  const older = page.locator('.lr-version-menu .lr-menu-item', { hasText: '2.x' })
  await expect(older).toBeVisible()
  await older.click()
  await expect(page).toHaveURL(/\/guide\/2\.x$/)
  await expect(page.locator('h1.lr-title')).toHaveText('LaRecipe 2.x')
})

test('the navigation sheet opens on phones without JavaScript', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/guide/3.x/installation')

  await expect(page.locator('#lr-sidebar')).toBeHidden()
  await page.locator('a.lr-menu-button').click()
  await expect(page.locator('#lr-sidebar')).toBeVisible()
  await page.locator('#lr-sidebar .lr-nav-link', { hasText: /^Components/ }).click()
  await expect(page).toHaveURL(/\/guide\/3\.x\/components$/)
})
