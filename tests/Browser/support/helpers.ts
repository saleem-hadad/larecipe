import type { Page } from '@playwright/test'

/** Mark the window, so a test can tell a client-side navigation from a full page load. */
export async function markWindow(page: Page): Promise<void> {
  await page.evaluate(() => {
    ;(window as Window & { __lrMarker?: string }).__lrMarker = 'still-here'
  })
}

export async function windowMarker(page: Page): Promise<string | undefined> {
  return page.evaluate(() => (window as Window & { __lrMarker?: string }).__lrMarker)
}

/** Wait until React has taken over the server page. */
export async function waitForApp(page: Page): Promise<void> {
  await page.waitForFunction(() => document.documentElement.classList.contains('lr-ready'))
}

/** Collect layout shifts from the very start of the page (attach before navigation). */
export async function recordLayoutShifts(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const shifts: Array<{ value: number; sources: string[] }> = []
    ;(window as Window & { __lrShifts?: typeof shifts }).__lrShifts = shifts
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries() as Array<PerformanceEntry & { value: number; hadRecentInput: boolean; sources?: Array<{ node?: Node }> }>) {
        if (entry.hadRecentInput) continue
        shifts.push({
          value: entry.value,
          sources: (entry.sources ?? []).map((source) => {
            const node = source.node as Element | undefined
            return node && 'className' in node ? `${node.nodeName}.${String(node.className).split(' ').join('.')}` : String(node?.nodeName)
          }),
        })
      }
    }).observe({ type: 'layout-shift', buffered: true })
  })
}

export async function layoutShifts(page: Page): Promise<Array<{ value: number; sources: string[] }>> {
  return page.evaluate(() => (window as Window & { __lrShifts?: Array<{ value: number; sources: string[] }> }).__lrShifts ?? [])
}

/** Bounding boxes of the regions that must not move when React takes over. */
export const TAKEOVER_SELECTORS = [
  '.lr-header',
  '.lr-brand',
  '.lr-search-trigger',
  '.lr-ask-button',
  '.lr-sidebar',
  '.lr-nav-link[aria-current="page"]',
  '.lr-page-header',
  '.lr-title',
  '.lr-lead',
  '.lr-provenance',
  '#lr-article',
  '.lr-rail',
  '.lr-toc',
  '.lr-page-actions',
  '.lr-ticket',
  '.lr-page-footer',
  '.lr-footer',
  '[data-lr-insert="article.after"]',
]

export async function boxes(page: Page, selectors: string[] = TAKEOVER_SELECTORS): Promise<Record<string, { x: number; y: number; width: number; height: number } | null>> {
  return page.evaluate((list) => {
    const result: Record<string, { x: number; y: number; width: number; height: number } | null> = {}
    for (const selector of list) {
      const element = document.querySelector(selector)
      if (!element) {
        result[selector] = null
        continue
      }
      const box = element.getBoundingClientRect()
      result[selector] = { x: Math.round(box.x * 10) / 10, y: Math.round((box.y + window.scrollY) * 10) / 10, width: Math.round(box.width * 10) / 10, height: Math.round(box.height * 10) / 10 }
    }
    return result
  }, selectors)
}
