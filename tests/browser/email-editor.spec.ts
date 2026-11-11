import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
test('polished email editing preserves blocks and supports narrow responsive previews', async ({ page }) => {
    test.setTimeout(120000);
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    const email = `editor-${Date.now()}@example.test`,
        password = randomBytes(24).toString('hex');
    const code = `$u=App\\Models\\User::factory()->create(['name'=>'Email Studio reviewer','email'=>${JSON.stringify(email)},'password'=>${JSON.stringify(password)}]);$o=App\\Models\\Organisation::create(['name'=>'Acorn Studio · Editor review','postal_address'=>'10 Example Street, London']);$o->members()->attach($u->id,['role'=>'owner']);echo $o->id;`;
    const id = execFileSync('php', ['artisan', 'tinker', '--execute=' + code], { encoding: 'utf8' }).trim();
    const base = `/organisations/${id}`;
    await page.route('https://assets.example.test/editor-image.png', (route) =>
        route.fulfill({ path: 'public/email-assets/studio-hero.png', contentType: 'image/png' }),
    );
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    await page.goto(base + '/templates');
    await expect(page.getByRole('heading', { name: 'Designed to make a good impression' })).toBeVisible();
    const thumbnails = page.locator('iframe[title$="thumbnail"]');
    for (let n = 0; n < (await thumbnails.count()); n++) {
        await thumbnails.nth(n).scrollIntoViewIfNeeded();
        await expect(thumbnails.nth(n).contentFrame().locator('body')).not.toBeEmpty();
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: 'test-results/email-library-desktop.png', fullPage: true, animations: 'disabled' });
    await page.getByRole('button', { name: 'Use template', exact: true }).first().click();
    const canvas = page.getByRole('textbox', { name: 'Email content', exact: true });
    await expect(canvas).toBeVisible();
    const originalUrl = page.url();
    await expect(page.getByText('Save your changes to update this preview.', { exact: true })).toHaveCount(0);
    await expect(canvas.locator('table[data-email-button] a')).toHaveText('Discover what is new');
    await canvas.locator('table[data-email-button]').click();
    await page.getByRole('button', { name: 'Edit button', exact: true }).click();
    await page.getByLabel('Button label', { exact: true }).fill('Discover our story');
    await page.getByLabel('Destination URL', { exact: true }).fill('javascript:alert(1)');
    await page.getByRole('button', { name: 'Apply button', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Use an HTTPS destination' })).toBeVisible();
    await page.getByLabel('Destination URL', { exact: true }).fill('https://example.com/story');
    await page.getByLabel('Alignment', { exact: true }).selectOption('center');
    await page.getByRole('button', { name: 'Apply button', exact: true }).click();
    await expect(canvas.locator('table[data-email-button] a')).toHaveText('Discover our story');
    await canvas.locator('p').last().click();
    await canvas.press('ControlOrMeta+End');
    await canvas.press('Enter');
    await page.getByRole('button', { name: 'Bold', exact: true }).click();
    await page.keyboard.type('A thoughtful addition.');
    await expect(canvas.locator('strong').filter({ hasText: 'A thoughtful addition.' })).toBeVisible();
    await page.getByRole('button', { name: 'Bold', exact: true }).click();
    await page.getByRole('button', { name: 'Italic', exact: true }).click();
    await page.keyboard.type(' With care.');
    await expect(canvas.locator('em').filter({ hasText: 'With care.' })).toBeVisible();
    await page.getByRole('button', { name: 'Italic', exact: true }).click();
    await page.getByRole('button', { name: 'Insert variable', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Last name', exact: true }).click();
    await expect(canvas).toContainText('{{last_name}}');
    await page.getByRole('button', { name: 'Insert image', exact: true }).click();
    await page.getByLabel('HTTPS image URL', { exact: true }).fill('http://insecure.example.test/image.png');
    await page.getByRole('button', { name: 'Apply image', exact: true }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Use HTTPS image' })).toBeVisible();
    await page.getByLabel('HTTPS image URL', { exact: true }).fill('https://assets.example.test/editor-image.png');
    await page.getByLabel('Alternative text', { exact: true }).fill('Original geometric studio artwork');
    await page.getByLabel('Linked destination (optional)', { exact: true }).fill('https://example.com/art');
    await page.getByRole('button', { name: 'Apply image', exact: true }).click();
    await expect(canvas.locator('div[data-email-image] img')).toHaveAttribute('alt', 'Original geometric studio artwork');
    await page.getByRole('button', { name: 'Save template', exact: true }).click();
    await expect(page.getByText('Template saved and sanitised.', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByText('Save your changes to update this preview.', { exact: true })).toHaveCount(0);
    await expect(canvas.locator('table[data-email-button] a')).toHaveText('Discover our story');
    await expect(canvas.locator('div[data-email-image] img')).toHaveAttribute('alt', 'Original geometric studio artwork');
    await expect(canvas).toContainText('Good things.');
    await expect(canvas).toContainText('A thoughtful addition.');
    await canvas.scrollIntoViewIfNeeded();
    await page.locator('.email-editor').screenshot({ path: 'test-results/email-editor-desktop.png', animations: 'disabled' });
    const frame = page.frameLocator('iframe[title="Template preview"]');
    await expect(frame.getByRole('link', { name: 'Discover our story', exact: true })).toHaveCSS('text-decoration-line', 'none');
    await page.getByRole('button', { name: 'Mobile preview', exact: true }).click();
    const iframe = page.locator('iframe[title="Template preview"]');
    expect(await iframe.evaluate((el) => el.clientWidth)).toBeGreaterThanOrEqual(370);
    expect(await iframe.evaluate((el) => el.clientWidth)).toBeLessThanOrEqual(375);
    await expect(frame.locator('h1')).toHaveCSS('font-size', '28px');
    expect(await frame.locator('html').evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true);
    await iframe.scrollIntoViewIfNeeded();
    await expect(frame.getByRole('link', { name: 'Discover our story', exact: true })).toBeVisible();
    await page
        .getByRole('region', { name: 'Email preview modes' })
        .screenshot({ path: 'test-results/email-preview-mobile.png', animations: 'disabled' });
    await page.getByRole('button', { name: 'Plain-text preview', exact: true }).click();
    await expect(page.getByLabel('Plain-text email preview')).toContainText('Hello Alex');
    await expect(page.getByLabel('Plain-text email preview')).not.toContainText('{{first_name}}');
    await page.getByRole('button', { name: 'Desktop preview', exact: true }).click();
    await iframe.scrollIntoViewIfNeeded();
    await expect(frame.getByRole('link', { name: 'Discover our story', exact: true })).toBeVisible();
    await page
        .getByRole('region', { name: 'Email preview modes' })
        .screenshot({ path: 'test-results/email-preview-desktop.png', animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    await canvas.scrollIntoViewIfNeeded();
    await expect.poll(() => page.locator('html').evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true);
    expect(await page.getByRole('toolbar', { name: 'Email formatting' }).evaluate((el) => el.clientHeight)).toBeLessThan(110);
    await page.locator('.email-editor').screenshot({ path: 'test-results/email-editor-mobile.png', animations: 'disabled' });
    await page.getByRole('button', { name: 'More editor tools', exact: true }).click();
    await expect(page.getByRole('menuitem', { name: 'Numbered list', exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Mobile preview', exact: true }).click();
    expect(await frame.locator('html').evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true);
    await page.getByRole('button', { name: 'Duplicate template', exact: true }).click();
    await expect(page).not.toHaveURL(originalUrl);
    await expect(canvas.locator('table[data-email-button] a')).toHaveText('Discover our story');
    await expect(canvas.locator('div[data-email-image] img')).toHaveAttribute('alt', 'Original geometric studio artwork');
    await canvas.locator('div[data-email-image]').click();
    await page.getByRole('button', { name: 'Edit image', exact: true }).click();
    await page.getByLabel('Alignment', { exact: true }).selectOption('right');
    await page.getByRole('button', { name: 'Apply image', exact: true }).click();
    await expect(canvas.locator('div[data-email-image]')).toHaveAttribute('data-align', 'right');
    await canvas.locator('div[data-email-image]').click();
    await page.getByRole('button', { name: 'Remove selected block', exact: true }).click();
    await expect(canvas.locator('div[data-email-image]')).toHaveCount(0);
    await page.getByRole('button', { name: 'More editor tools', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Undo', exact: true }).click();
    await expect(canvas.locator('div[data-email-image]')).toHaveCount(1);
    await page.getByRole('button', { name: 'More editor tools', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Redo', exact: true }).click();
    await expect(canvas.locator('div[data-email-image]')).toHaveCount(0);
    await canvas.locator('p').first().click();
    await page.getByRole('button', { name: 'More editor tools', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Heading 3', exact: true }).click();
    await expect(canvas.locator('h3')).toContainText('{{sender_name}}');
    await page.getByRole('button', { name: 'More editor tools', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Numbered list', exact: true }).click();
    await expect(canvas.locator('ol')).toContainText('{{sender_name}}');
    await page.getByRole('button', { name: 'More editor tools', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Align center', exact: true }).click();
    await expect(canvas.locator('ol p')).toHaveCSS('text-align', 'center');
    await page.goto(originalUrl);
    await expect(canvas.locator('div[data-email-image] img')).toHaveAttribute('alt', 'Original geometric studio artwork');
    for (const width of [320, 768]) {
        await page.setViewportSize({ width, height: 844 });
        await expect.poll(() => page.locator('html').evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true);
    }
    expect(errors).toEqual([]);
});

test('existing table content inline colour and small logo dimensions survive an editor save', async ({ page }) => {
    const email = `legacy-editor-${Date.now()}@example.test`,
        password = randomBytes(24).toString('hex');
    const code = `$u=App\\Models\\User::factory()->create(['email'=>${JSON.stringify(email)},'password'=>${JSON.stringify(password)}]);$o=App\\Models\\Organisation::create(['name'=>'Legacy editor preservation']);$o->members()->attach($u->id,['role'=>'owner']);$html='<table width="100%"><tbody><tr><td bgcolor="#edf3ef" colspan="2"><p><span style="color:#234567;">Existing customer design</span></p><img src="https://assets.example.test/editor-image.png" alt="Existing small logo" width="120"></td></tr></tbody></table><p>{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>';$s=app(App\\Services\\EmailContentService::class);$t=App\\Models\\EmailTemplate::create(['organisation_id'=>$o->id,'name'=>'User-modified legacy template','category'=>'Existing','subject'=>'Preserved design','html'=>$s->clean($html),'text_body'=>$s->text($html)]);echo json_encode(['company'=>$o->id,'template'=>$t->id]);`;
    const ids = JSON.parse(execFileSync('php', ['artisan', 'tinker', '--execute=' + code], { encoding: 'utf8' }).trim());
    await page.route('https://assets.example.test/editor-image.png', (route) =>
        route.fulfill({ path: 'public/email-assets/studio-hero.png', contentType: 'image/png' }),
    );
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
    await page.goto(`/organisations/${ids.company}/templates/${ids.template}`);
    const canvas = page.getByRole('textbox', { name: 'Email content', exact: true });
    await expect(canvas.getByRole('img', { name: 'Existing small logo' })).toHaveAttribute('width', '120');
    await expect(canvas.locator('span').filter({ hasText: 'Existing customer design' })).toHaveCSS('color', 'rgb(35, 69, 103)');
    await expect(canvas.locator('td')).toHaveAttribute('bgcolor', '#edf3ef');
    await page.getByRole('button', { name: 'Save template', exact: true }).click();
    await expect(page.getByText('Template saved and sanitised.', { exact: true })).toBeVisible();
    await page.reload();
    await expect(canvas.getByRole('img', { name: 'Existing small logo' })).toHaveAttribute('width', '120');
    await expect(canvas.locator('span').filter({ hasText: 'Existing customer design' })).toHaveCSS('color', 'rgb(35, 69, 103)');
    await expect(canvas.locator('td')).toHaveAttribute('colspan', '2');
});
