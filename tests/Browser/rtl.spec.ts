import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

/** Arabic is a peer rendering: mirrored layout, code and numerals left to right. */
for (const path of ['/guide/ar/3.x/installation', '/handbook/ar/2.x']) {
  test(`${path} renders right to left`, async ({ page }, testInfo) => {
    await page.goto(path)
    await waitForApp(page)
    await page.evaluate(() => document.fonts.ready)

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar')

    // The sidebar sits on the right, the rail on the left.
    const sidebar = await page.locator('.lr-sidebar').boundingBox()
    const main = await page.locator('.lr-main').boundingBox()
    expect(sidebar && main && sidebar.x > main.x).toBe(true)

    const rail = await page.locator('.lr-rail').boundingBox()
    if (rail && main) expect(rail.x).toBeLessThan(main.x)

    // UI strings are Arabic, labels drop the mono uppercase.
    await expect(page.locator('#lr-actions-label')).toHaveText('للوكلاء')
    const labelFont = await page.locator('#lr-actions-label').evaluate((node) => getComputedStyle(node).textTransform)
    expect(labelFont).toBe('none')

    await testInfo.attach('rtl', { body: await page.screenshot({ fullPage: false }), contentType: 'image/png' })
  })
}

test('code, versions and hashes stay left to right inside Arabic pages', async ({ page }) => {
  await page.goto('/guide/ar/3.x/installation')
  await waitForApp(page)

  const codeDirection = await page.locator('figure.lr-code pre').first().evaluate((node) => getComputedStyle(node).direction)
  expect(codeDirection).toBe('ltr')

  const versionDirection = await page.locator('.lr-version-button .lr-ltr').evaluate((node) => getComputedStyle(node).direction)
  expect(versionDirection).toBe('ltr')
  await expect(page.locator('.lr-version-button')).toHaveText('3.x')

  await expect(page.locator('.lr-provenance')).toContainText('حُدّث')
  await expect(page.locator('.lr-provenance .lr-hash')).toHaveText('9f3a2c1')
  const hashDirection = await page.locator('.lr-provenance .lr-hash').evaluate((node) => getComputedStyle(node).direction)
  expect(hashDirection).toBe('ltr')

  await expect(page.locator('.lr-ticket')).toContainText('آخر تغيير')
  await expect(page.locator('.lr-ticket bdi')).toHaveText('@saleem')
})
