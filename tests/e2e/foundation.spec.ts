import { devices, expect, test, type Page } from '@playwright/test'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname } from 'node:path'

async function login(page: Page) {
    await page.goto('/signin')
    await page.locator('#auth-email').fill('foundation@example.test')
    await page.locator('#auth-password').fill('foundation-test-password')
    await page.locator('button[type="submit"]').click()
    await expect(page).toHaveURL(/\/app$/)
}

test('public pages have canonical, structured data and no browser failures', async ({ page }) => {
    const errors: string[] = []
    page.on('pageerror', error => errors.push(error.message))
    for (const path of ['/', '/blog', '/blog/chair-dimensions-guide', '/contact', '/privacy']) {
        const response = await page.goto(path)
        expect(response?.status()).toBe(200)
        await expect(page.locator('h1').first()).toBeVisible()
        const canonical = await page.locator('link[rel="canonical"]').getAttribute('href')
        expect(new URL(canonical!).pathname).toBe(path)
        for (const json of await page.locator('script[type="application/ld+json"]').allTextContents()) {
            expect(JSON.parse(json)).toBeTruthy()
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    }
    expect(errors).toEqual([])
})

test('robots, sitemap, private drafts and API authentication', async ({ request }) => {
    const robots = await request.get('/robots.txt')
    expect(robots.status()).toBe(200)
    expect(await robots.text()).toContain('Disallow: /app')
    const sitemap = await request.get('/sitemap.xml')
    expect(sitemap.status()).toBe(200)
    expect(await sitemap.text()).toContain('/blog/chair-dimensions-guide')
    expect(await sitemap.text()).not.toContain('private-draft')
    expect((await request.get('/blog/private-draft')).status()).toBe(404)
    const api = await request.get('/api/v1/blog/posts')
    expect(api.status()).toBe(401)
    expect(api.headers()['x-robots-tag']).toContain('noindex')
})

test('signin and dashboard navigation survive full page and Inertia visits', async ({ page }) => {
    const errors: string[] = []
    page.on('pageerror', error => errors.push(error.message))
    await login(page)
    await expect(page.locator('h1').first()).toBeVisible()
    await page.screenshot({ path: `test-results/dashboard-${test.info().project.name}.png`, fullPage: true })
    for (const path of ['/app/projects', '/app/photo-to-drawing', '/app/photo-to-model', '/app']) {
        await page.goto(path)
        await expect(page.locator('h1').first()).toBeVisible()
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/)
    }
    // Exercise a real Inertia navigation, not only server document requests.
    const menu = page.getByRole('button', { name: 'Open navigation', exact: true })
    if (await menu.isVisible()) await menu.click()
    const projects = page.locator('a[href$="/app/projects"]').first()
    if (await projects.isVisible()) {
        await projects.click()
        await expect(page).toHaveURL(/\/app\/projects$/)
    }
    expect(errors).toEqual([])
})

test('homepage screenshot and optional baseline visual comparison', async ({ page, browser }) => {
    await page.goto('/')
    await page.evaluate(() => document.fonts.ready)
    await page.screenshot({ path: `test-results/home-${test.info().project.name}.png`, fullPage: true })

    if (!process.env.E2E_BASELINE_URL) return
    test.setTimeout(90_000)
    const baseline = await browser.newContext({
        ...(test.info().project.name === 'mobile' ? devices['iPhone 13'] : devices['Desktop Chrome']),
        viewport: page.viewportSize()!,
    })
    const before = await baseline.newPage()
    for (const path of ['/', '/app']) {
        if (path === '/app') {
            await login(page)
            await before.goto(`${process.env.E2E_BASELINE_URL}/signin`)
            await before.locator('#auth-email').fill('foundation@example.test')
            await before.locator('#auth-password').fill('foundation-test-password')
            await before.locator('button[type="submit"]').click()
            await expect(before).toHaveURL(/\/app$/)
        } else {
            await before.goto(`${process.env.E2E_BASELINE_URL}${path}`)
        }
        await Promise.all([page.evaluate(() => document.fonts.ready), before.evaluate(() => document.fonts.ready)])
        // Decode lazy illustration assets on both sides before comparing.
        for (const target of [page, before]) {
            await target.evaluate(async () => {
                document.querySelectorAll('video').forEach(video => { video.pause(); video.currentTime = 0 })
                await Promise.all(Array.from(document.images).map(image => {
                    image.loading = 'eager'
                    return image.decode().catch(() => {})
                }))
            })
        }
        const current = await page.screenshot({ fullPage: true, animations: 'disabled', scale: 'css', mask: [page.locator('video')], path: test.info().outputPath(`current-${path === '/' ? 'home' : 'dashboard'}.png`) })
        const original = await before.screenshot({ fullPage: true, animations: 'disabled', scale: 'css', mask: [before.locator('video')], path: test.info().outputPath(`baseline-${path === '/' ? 'home' : 'dashboard'}.png`) })
        await test.info().attach(`current-${path === '/' ? 'home' : 'dashboard'}`, { body: current, contentType: 'image/png' })
        await test.info().attach(`baseline-${path === '/' ? 'home' : 'dashboard'}`, { body: original, contentType: 'image/png' })
        expect(await page.locator('body').innerText()).toBe(await before.locator('body').innerText())
        const name = `${path === '/' ? 'home' : 'dashboard'}-upstream-baseline.png`
        const reference = test.info().snapshotPath(name)
        mkdirSync(dirname(reference), { recursive: true })
        writeFileSync(reference, original)
        // Playwright's comparator ignores anti-aliasing noise, not layout or text changes.
        expect(current).toMatchSnapshot(name, { maxDiffPixels: 0, threshold: 0.1 })
    }
    await baseline.close()
})
