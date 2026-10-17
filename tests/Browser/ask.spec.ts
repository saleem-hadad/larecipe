import { expect, test } from '@playwright/test'
import { waitForApp } from './support/helpers'

const modifier = process.platform === 'darwin' ? 'Meta' : 'Control'

test.beforeEach(async ({ page }) => {
  await page.goto('/guide/3.x/installation')
  await waitForApp(page)
})

test('⌘I opens Ask; the answer streams and cites pages as tickets', async ({ page }) => {
  await page.keyboard.press(`${modifier}+i`)
  const panel = page.getByRole('dialog', { name: 'Ask the docs' })
  await expect(panel).toBeVisible()
  await expect(panel.getByRole('textbox')).toBeFocused()

  const request = page.waitForRequest('**/_workbench/ask')
  await page.keyboard.type('How do I connect Claude Code?')
  await page.keyboard.press('Enter')
  const sent = await request
  expect(sent.postDataJSON()).toMatchObject({ path: 'installation', version: '3.x', locale: 'en' })

  await expect(panel.locator('.lr-ask-question')).toHaveText('How do I connect Claude Code?')
  await expect(panel.locator('.lr-ask-answer')).toContainText('Add the MCP server to Claude Code')
  await expect(panel.locator('.lr-ask-answer pre code')).toContainText('claude mcp add')

  const citations = panel.locator('.lr-citation')
  await expect(citations).toHaveCount(2)
  await expect(citations.first()).toContainText('Connect your agents')
  await expect(citations.first().locator('.lr-citation-path')).toHaveText('3.x/agents#the-mcp-server')

  // A citation navigates in place and the panel stays open.
  await citations.first().click()
  await expect(page.locator('h1.lr-title')).toHaveText('Connect your agents')
  await expect(panel).toBeVisible()

  await panel.getByRole('textbox').focus()
  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
})

test('an answer that cites nothing says the docs may not cover it', async ({ page }) => {
  await page.locator('#lr-ask-bar-input').fill('What does the enterprise pricing cost?')
  await page.keyboard.press('Enter')

  const panel = page.getByRole('dialog', { name: 'Ask the docs' })
  await expect(panel.locator('.lr-ask-answer')).toContainText('The docs do not cover pricing')
  await expect(panel.getByRole('note')).toContainText('No page was cited for this answer')
  await expect(panel.locator('.lr-citation')).toHaveCount(0)

  await panel.getByRole('button', { name: /Search for/ }).click()
  await expect(page.getByRole('dialog', { name: 'Search' })).toBeVisible()
})

test('a failed request is stated plainly', async ({ page }) => {
  await page.route('**/_workbench/ask', (route) => route.fulfill({ status: 503, body: 'down' }))
  await page.keyboard.press(`${modifier}+i`)
  await expect(page.getByRole('dialog', { name: 'Ask the docs' }).getByRole('textbox')).toBeFocused()
  await page.keyboard.type('Anything')
  await page.keyboard.press('Enter')
  await expect(page.getByRole('alert')).toContainText('Ask is not available right now')
})
