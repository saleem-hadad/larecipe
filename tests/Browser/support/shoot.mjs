/**
 * Screenshots for design review: node tests/Browser/support/shoot.mjs <out-dir> [path=...] [width=...] [theme=light|dark] [js=0]
 * Not part of the test suite; a tool for checking the UI by eye.
 */
import { chromium } from '@playwright/test'

const [outDir = 'test-results/shots', ...args] = process.argv.slice(2)
const options = Object.fromEntries(args.map((arg) => arg.split('=')))
const base = process.env.LARECIPE_URL ?? 'http://127.0.0.1:8771'
const paths = (options.path ?? '/guide/3.x/installation').split(',')
const width = Number(options.width ?? 1440)
const height = Number(options.height ?? 1000)
const theme = options.theme ?? 'light'
const javaScriptEnabled = options.js !== '0'
const full = options.full === '1'

const browser = await chromium.launch()
const context = await browser.newContext({ viewport: { width, height }, colorScheme: theme, javaScriptEnabled, deviceScaleFactor: Number(options.scale ?? 1) })
const page = await context.newPage()
page.on('console', (message) => {
  if (message.type() === 'error' || message.type() === 'warning') console.log(`[${message.type()}] ${message.text()}`)
})
page.on('pageerror', (error) => console.log(`[pageerror] ${error.message}`))

for (const path of paths) {
  await page.goto(base + path, { waitUntil: 'networkidle' })
  if (options.action === 'palette') {
    await page.keyboard.press('Control+k')
    await page.waitForTimeout(400)
    if (options.query) await page.keyboard.type(options.query, { delay: 30 })
    await page.waitForTimeout(500)
  }
  if (options.action === 'ask') {
    await page.keyboard.press('Control+i')
    await page.waitForTimeout(300)
    if (options.query) {
      await page.keyboard.type(options.query, { delay: 10 })
      await page.keyboard.press('Enter')
      await page.waitForTimeout(2500)
    }
  }
  if (options.scroll) {
    await page.evaluate((y) => window.scrollTo(0, Number(y)), options.scroll)
    await page.waitForTimeout(300)
  }
  const name = `${outDir}/${path.replace(/[^a-z0-9]+/gi, '_')}-${width}-${theme}${javaScriptEnabled ? '' : '-nojs'}${options.action ? '-' + options.action : ''}${options.tag ? '-' + options.tag : ''}.png`
  await page.screenshot({ path: name, fullPage: full })
  console.log(name)
}

await browser.close()
