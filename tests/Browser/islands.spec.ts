import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

test.use({ permissions: ['clipboard-read', 'clipboard-write'] })

test.beforeEach(async ({ page }) => {
  await page.goto('/guide/3.x/components')
  await waitForApp(page)
})

test('tabs are a keyboard-operable tab list', async ({ page }) => {
  const tablist = page.getByRole('tablist').first()
  const tabs = tablist.getByRole('tab')
  await expect(tabs).toHaveText(['Composer', 'Laravel Cloud', 'Docker'])
  await expect(tabs.first()).toHaveAttribute('aria-selected', 'true')

  await tabs.first().focus()
  await page.keyboard.press('ArrowRight')
  await expect(tabs.nth(1)).toBeFocused()
  await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('tabpanel', { name: 'Laravel Cloud' })).toContainText('Provenance is compiled at build time')
  await expect(page.getByText('Install with Composer and publish nothing')).toBeHidden()

  await page.keyboard.press('End')
  await expect(tabs.nth(2)).toHaveAttribute('aria-selected', 'true')
})

test('code groups switch blocks, and groups with the same sync key move together', async ({ page }) => {
  const groups = page.locator('[data-lr-component="CodeGroup"]')
  const first = groups.nth(0)
  const second = groups.nth(1)

  await first.getByRole('tab', { name: 'pnpm' }).click()
  await expect(first.getByRole('tabpanel')).toContainText('pnpm add -D @larecipe/vite')
  await expect(second.getByRole('tab', { name: 'pnpm' })).toHaveAttribute('aria-selected', 'true')
  await expect(second.getByRole('tabpanel')).toContainText('pnpm build')
})

test('copy buttons copy the code and say so for a moment', async ({ page }) => {
  const block = page.locator('figure.lr-code').first()
  const copy = block.locator('.lr-code-copy')
  await copy.click()
  await expect(copy).toHaveAttribute('data-copied', 'true')
  await expect(copy).toContainText('Copied')
  const clipboard = await page.evaluate(() => navigator.clipboard.readText())
  expect(clipboard).toContain('public function __invoke(Request $request): Response')
  expect(clipboard).toContain('return response()->noContent();')
  await expect(copy).toContainText('Copy', { timeout: 3000 })
})

test('headings get anchors that link to themselves', async ({ page }) => {
  const anchor = page.locator('#callouts .lr-anchor')
  await expect(anchor).toHaveAttribute('href', '#callouts')
  await expect(anchor).toHaveAttribute('aria-label', /Link to this section: Callouts/)
})

test('frames zoom into a dialog', async ({ page }) => {
  const frame = page.locator('figure.lr-frame').first()
  await frame.scrollIntoViewIfNeeded()
  await frame.hover()
  await frame.getByRole('button', { name: 'Zoom image' }).click()
  const zoom = page.getByRole('dialog', { name: /The loop/ })
  await expect(zoom).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(zoom).toBeHidden()
})

test('video loads the player only when the reader asks', async ({ page }) => {
  await page.route(/youtube-nocookie\.com/, (route) => route.fulfill({ status: 200, contentType: 'text/html', body: '<p>player</p>' }))
  const video = page.locator('figure.lr-video:not(.lr-video-placeholder)')
  await video.scrollIntoViewIfNeeded()
  await expect(video.locator('iframe')).toHaveCount(0)
  await video.getByRole('button', { name: /Play video/ }).click()
  await expect(video.locator('iframe')).toHaveAttribute('src', /youtube-nocookie\.com\/embed\/aqz-KE-bpKQ\?autoplay=1/)
})

test('diagrams and math render when they come into view, keeping their source', async ({ page }) => {
  await page.locator('#diagrams').scrollIntoViewIfNeeded()
  await expect(page.locator('.lr-mermaid-render svg')).toBeVisible({ timeout: 15_000 })
  await expect(page.locator('details.lr-mermaid-source pre.lr-mermaid')).toHaveCount(1)

  await page.locator('#math').scrollIntoViewIfNeeded()
  await expect(page.locator('.lr-math .katex')).toBeVisible({ timeout: 15_000 })
})
