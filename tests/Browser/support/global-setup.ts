import { execSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * The browser tests need the built UI (dist/) and the workbench's example
 * extension (built by @larecipe/vite). Build whichever is missing.
 */
export default function globalSetup(): void {
  const root = resolve(__dirname, '../../..')

  if (!existsSync(resolve(root, 'dist/.vite/manifest.json'))) {
    execSync('pnpm build', { cwd: root, stdio: 'inherit' })
  }

  if (!existsSync(resolve(root, 'workbench/public/build/larecipe/manifest.json'))) {
    execSync('pnpm --filter @larecipe/workbench build', { cwd: root, stdio: 'inherit' })
  }
}
