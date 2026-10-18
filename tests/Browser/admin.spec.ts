import { expect, test, type Page } from '@playwright/test'
import { execSync } from 'node:child_process'
import { resolve } from 'node:path'

/**
 * The admin UI's happy path against the workbench's "runbooks" database set
 * (workbench/app/Providers/AdminWorkbenchServiceProvider.php): a manager signs
 * in, edits a page with the live server preview, resolves a docs-migration
 * conflict by editing a merge, and publishes an agent's draft from the pass.
 * Each step is checked on the reader's side too.
 */
test.describe.configure({ mode: 'serial' })

const root = resolve(__dirname, '../..')
const shots = resolve(root, 'test-results/browser/admin')

test.beforeAll(() => {
  execSync('vendor/bin/testbench workbench:seed-runbooks', { cwd: root, stdio: 'pipe' })
})

async function signIn(page: Page, who: 'manager' | 'reader' = 'manager'): Promise<void> {
  await page.goto(`/workbench/sign-in/${who}`)
}

/** Put the cursor at the end of the CodeMirror document and type. */
async function typeAtEnd(page: Page, text: string): Promise<void> {
  await page.locator('.cm-content').click()
  await page.keyboard.press('ControlOrMeta+End')
  await page.keyboard.type(text)
}

test('keeps the admin closed to people the gate does not let in', async ({ page }) => {
  await signIn(page, 'reader')
  const response = await page.goto('/runbooks/_admin')
  expect(response?.status()).toBe(403)
})

test('edits a page with the live preview and publishes it', async ({ page }) => {
  await signIn(page)
  await expect(page.getByRole('heading', { level: 1, name: 'Runbooks' })).toBeVisible()
  await expect(page.getByRole('navigation', { name: 'Admin' }).getByText('2', { exact: true })).toBeVisible()

  await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: 'Pages' }).click()
  await page.getByRole('link', { name: 'Runbooks', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1, name: 'Runbooks' })).toBeVisible()

  await typeAtEnd(page, '\nCheck the status page before paging anyone.\n')
  await expect(page.getByTestId('preview')).toContainText('Check the status page before paging anyone.')
  await page.screenshot({ path: `${shots}/editor.png`, fullPage: true })

  await page.getByLabel('What changed, in one line').fill('Mention the status page')
  await page.getByRole('button', { name: 'Publish', exact: true }).click()
  await expect(page.getByText('The page is live.')).toBeVisible()

  await page.goto('/runbooks')
  await expect(page.locator('#lr-article')).toContainText('Check the status page before paging anyone.')
})

test('resolves a docs-migration conflict by editing a merge', async ({ page }) => {
  await signIn(page)
  await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name: /The pass/ }).click()

  const conflict = page.getByRole('article', { name: /Escalation/ })
  await expect(conflict).toContainText('2026_10_17_141502_docs_pagerduty')
  await page.screenshot({ path: `${shots}/pass.png`, fullPage: true })
  await conflict.getByRole('button', { name: 'Resolve' }).click()

  await expect(page.getByText('Page the on-call engineer, then open an incident.')).toBeVisible()
  await expect(page.getByText('Page the on-call engineer through PagerDuty.')).toBeVisible()
  await page.screenshot({ path: `${shots}/conflict.png`, fullPage: true })

  await page.getByRole('button', { name: 'Edit a merge' }).click()
  await typeAtEnd(page, 'Then open an incident.\n')
  await expect(page.getByTestId('preview')).toContainText('Then open an incident.')
  await page.getByRole('button', { name: 'Publish the merge' }).click()
  await expect(page.getByText('The merge is live.')).toBeVisible()

  await page.goto('/runbooks/on-call/escalation')
  await expect(page.locator('#lr-article')).toContainText('Page the on-call engineer through PagerDuty.')
  await expect(page.locator('#lr-article')).toContainText('Then open an incident.')
})

test('publishes an agent draft from the pass', async ({ page }) => {
  await signIn(page)
  await page.goto('/runbooks/_admin/review')

  const draft = page.getByRole('article', { name: /Rolling back a deploy/ })
  await expect(draft).toContainText('claude-code')
  await draft.getByRole('button', { name: 'Publish' }).click()
  await expect(page.getByText(/is live, signed off by Ada Manager/)).toBeVisible()
  await expect(page.getByText('Nothing at the pass')).toBeVisible()
  await page.screenshot({ path: `${shots}/pass-empty.png`, fullPage: true })

  await page.goto('/runbooks/deploys/rollback')
  await expect(page.locator('#lr-article')).toContainText('tell the incident channel')
})
