import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

/**
 * The example extension (workbench/resources/larecipe): a host's own React
 * component, built by @larecipe/vite, placed at article.after by the fixture
 * manifest, with a server fallback for readers without JavaScript.
 */
test('the extension shares the runtime’s single React and renders at its insert point', async ({ page }) => {
  const modules: string[] = []
  page.on('request', (request) => {
    if (request.resourceType() === 'script') modules.push(new URL(request.url()).pathname)
  })

  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const form = page.locator('[data-lr-insert="article.after"] [data-lr-subscribe="react"]')
  await expect(form).toBeVisible()
  // useLaRecipe() read the page: the extension sees the runtime's context, so
  // it shares the runtime's React and @larecipe/ui (a second copy would throw).
  await expect(form).toHaveAttribute('data-page', 'installation')
  await expect(page.locator('[data-lr-subscribe="fallback"]')).toHaveCount(0)

  // Every module comes from the LaRecipe runtime or the host's extension build,
  // and the extension itself carries no React.
  const extension = modules.find((path) => path.startsWith('/build/larecipe/'))
  expect(extension).toBeDefined()
  expect(modules.every((path) => path.startsWith('/larecipe-assets/') || path.startsWith('/build/larecipe/'))).toBe(true)
  const source = await (await page.request.get(extension as string)).text()
  expect(source).toContain('from"react"')
  expect(source).toContain('from"@larecipe/ui"')
  expect(source).not.toContain('react.production')
  expect(source.length).toBeLessThan(10_000)

  const versions = await page.evaluate(async () => {
    const map = JSON.parse(document.querySelector('script[type="importmap"]')?.textContent ?? '{}') as { imports: Record<string, string> }
    return map.imports
  })
  expect(Object.keys(versions)).toEqual(expect.arrayContaining(['react', 'react-dom', 'react-dom/client', 'react/jsx-runtime', '@larecipe/ui']))
})

test('the React form posts through request() with the session and XSRF token', async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)

  const form = page.locator('[data-lr-subscribe="react"]')
  await form.getByLabel('Email address').fill('reader@example.com')
  const request = page.waitForRequest('**/newsletter/subscribe')
  await form.getByRole('button', { name: 'Subscribe' }).click()
  const sent = await request

  expect(sent.headers()['accept']).toBe('application/json')
  expect(sent.headers()['x-xsrf-token']).toBeTruthy()
  expect(sent.postDataJSON()).toMatchObject({ email: 'reader@example.com', list: 'larecipe', source: 'installation' })
  await expect(page.locator('[data-lr-subscribe="react"]')).toContainText('Thanks. You are on the list.')
})

test('a page can opt out of an insert point', async ({ page }) => {
  await page.goto('/guide/3.x/guides/long-page')
  await waitForApp(page)
  await expect(page.locator('[data-lr-insert="article.after"]')).toHaveCount(0)
})

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false })

  test('the fallback form works', async ({ page }) => {
    await page.goto('/guide/3.x/installation')

    const form = page.locator('[data-lr-subscribe="fallback"]')
    await expect(form).toBeVisible()
    await form.getByLabel('Email address').fill('reader@example.com')
    await form.getByRole('button', { name: 'Subscribe' }).click()

    await expect(page).toHaveURL(/\/newsletter\?subscribed=1$/)
    await expect(page.getByRole('heading', { name: 'Thanks. You are on the list.' })).toBeVisible()
  })
})
