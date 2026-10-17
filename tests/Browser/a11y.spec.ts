import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { waitForApp } from './support/helpers'

/** WCAG 2.2 AA across the reading experience, both themes, RTL and the overlays. */
const modifier = process.platform === 'darwin' ? 'Meta' : 'Control'

async function audit(page: Page, include?: string) {
  // Colors are measured at rest: let entry motion (the ticket print) finish first.
  await page.waitForFunction(() => document.getAnimations().every((animation) => animation.playState !== 'running'))
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
  if (include) builder = builder.include(include)
  const results = await builder.analyze()
  const summary = results.violations.map((violation) => `${violation.id}: ${violation.help}\n  ${violation.nodes.map((node) => node.target.join(' ')).slice(0, 5).join('\n  ')}`)
  expect(summary, summary.join('\n')).toEqual([])
}

const pages: Array<{ path: string; scheme: 'light' | 'dark'; width?: number }> = [
  { path: '/guide/3.x/installation', scheme: 'light' },
  { path: '/guide/3.x/installation', scheme: 'dark' },
  { path: '/guide/3.x/components', scheme: 'light' },
  { path: '/guide/3.x/components', scheme: 'dark' },
  { path: '/guide/3.x/guides/authoring', scheme: 'light' },
  { path: '/guide/ar/3.x/installation', scheme: 'light' },
  { path: '/guide/3.x/installation', scheme: 'light', width: 390 },
  { path: '/docs/installation', scheme: 'light' },
]

for (const { path, scheme, width } of pages) {
  test(`${path} (${scheme}${width ? `, ${width}px` : ''}) has no WCAG 2.2 AA violations`, async ({ page }) => {
    await page.emulateMedia({ colorScheme: scheme })
    if (width) await page.setViewportSize({ width, height: 844 })
    await page.goto(path)
    await waitForApp(page)
    await page.evaluate(() => document.fonts.ready)
    await audit(page)
  })
}

test('the command palette is accessible', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await page.keyboard.press(`${modifier}+k`)
  await page.getByRole('combobox').fill('install')
  await expect(page.getByRole('option').nth(1)).toBeVisible()
  await audit(page, '.lr-palette')
})

test('the Ask panel is accessible', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await page.keyboard.press(`${modifier}+i`)
  const panel = page.getByRole('dialog', { name: 'Ask the docs' })
  await panel.getByRole('textbox').fill('How do I connect Claude Code?')
  await page.keyboard.press('Enter')
  await expect(panel.locator('.lr-citation').first()).toBeVisible()
  await audit(page, '.lr-ask-panel')
})

test('the not found page is accessible', async ({ page }) => {
  await page.goto('/guide/3.x/nothing-here')
  await audit(page)
})

test('keyboard users can reach the article from the skip link', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
  await page.keyboard.press('Tab')
  const skip = page.locator('.lr-skip')
  await expect(skip).toBeFocused()
  await expect(skip).toBeInViewport()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/#lr-article$/)
})
