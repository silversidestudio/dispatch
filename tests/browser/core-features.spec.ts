import { expect, Page, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
async function login(page: Page, name: string, email: string, password: string) {
    const code = `$u=new App\\Models\\User; $u->name=${JSON.stringify(name)}; $u->email=${JSON.stringify(email)}; $u->password=${JSON.stringify(password)}; $u->email_verified_at=now(); $u->save();`;
    execFileSync('php', ['artisan', 'tinker', '--execute=' + code]);
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/organisations/);
}
test('platform company to owner contacts import campaign simulation and reporting', async ({ browser }) => {
    test.setTimeout(150000);
    const stamp = Date.now();
    const password = randomBytes(24).toString('hex');
    const adminEmail = `e2e-admin-${stamp}@example.test`,
        ownerEmail = `e2e-owner-${stamp}@example.test`,
        teamEmail = `e2e-team-${stamp}@example.test`;
    const adminContext = await browser.newContext(),
        ownerContext = await browser.newContext(),
        teamContext = await browser.newContext();
    const admin = await adminContext.newPage(),
        owner = await ownerContext.newPage(),
        team = await teamContext.newPage();
    const errors: string[] = [];
    for (const p of [admin, owner, team]) p.on('pageerror', (e) => errors.push(e.message));
    await login(owner, 'Company Owner', ownerEmail, password);
    await login(team, 'Company Colleague', teamEmail, password);
    await login(admin, 'Platform Reviewer', adminEmail, password);
    execFileSync('php', ['artisan', 'platform:promote', adminEmail]);
    await admin.goto('/platform');
    await admin.getByLabel('Company name', { exact: true }).fill(`E2E Company ${stamp}`);
    await admin.getByLabel('Owner email', { exact: true }).fill(ownerEmail);
    await admin.getByRole('button', { name: 'Create company', exact: true }).click();
    await expect(admin).toHaveURL(/platform\/companies\/\d+/);
    const id = admin.url().split('/').pop();
    const base = `/organisations/${id}`;
    await owner.goto('/organisations');
    await owner.getByRole('button', { name: 'Open workspace', exact: true }).click();
    await expect(owner.getByRole('heading', { name: 'Workspace overview' })).toBeVisible();
    await owner.goto(base);
    await owner.getByLabel('Sender postal address').fill('10 Example Road, London, UK');
    await owner.getByRole('button', { name: 'Save changes', exact: true }).click();
    await expect(owner.getByText('Organisation updated.', { exact: true })).toBeVisible();
    await admin.getByLabel('Invitation email', { exact: true }).fill(teamEmail);
    await admin.getByRole('button', { name: 'Create invitation', exact: true }).click();
    const invitation = await admin.getByLabel('Invitation link', { exact: true }).inputValue();
    await team.goto(invitation);
    await team.getByRole('button', { name: 'Accept invitation' }).click();
    await expect(team).toHaveURL(/dashboard/);
    await owner.goto(base);
    await expect(owner.getByText(teamEmail, { exact: true })).toBeVisible();
    await owner.goto(base + '/imports');
    await owner.getByLabel('CSV file').setInputFiles({
        name: 'company-contacts.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(
            `email,first_name\nsuccess-${stamp}@example.test,Alex\ntemporary-${stamp}@example.test,Taylor\npermanent-${stamp}@example.test,Jordan\ninvalid,Bad\n`,
        ),
    });
    await owner.getByRole('button', { name: 'Upload and preview' }).click();
    await expect(owner).toHaveURL(/imports\/\d+/);
    await owner.getByRole('button', { name: 'Import contacts', exact: true }).click();
    await expect(owner.getByText('Completed', { exact: true })).toBeVisible({ timeout: 30000 });
    await expect(owner.getByText('Row 5: Rejected', { exact: false })).toBeVisible();
    for (const local of ['success', 'temporary', 'permanent']) {
        await owner.goto(base + '/contacts');
        await owner.getByRole('link', { name: `${local}-${stamp}@example.test`, exact: true }).click();
        await expect(owner.getByText(/Eligible for marketing/)).toBeVisible();
        await expect(
            owner.getByText(/Imported from CSV using the workspace eligibility setting|Eligibility assigned automatically by the configured CSV import policy/, {
                exact: true,
            }),
        ).toBeVisible();
    }
    await owner.goto(base + '/audiences');
    await owner.getByLabel('List name', { exact: true }).fill('Company newsletter');
    await owner.getByRole('button', { name: 'Create list', exact: true }).click();
    await owner.getByRole('link', { name: /Company newsletter/ }).click();
    await owner.getByRole('checkbox', { name: 'Select page', exact: true }).check();
    await owner.getByRole('button', { name: 'Add selected', exact: true }).click();
    await expect(owner.getByText(/3 current eligible recipients/)).toBeVisible();
    const importedMember = owner.getByRole('row').filter({ hasText: `success-${stamp}@example.test` });
    await expect(importedMember.getByText('Included in list', { exact: true })).toBeAttached();
    await expect(importedMember.getByText('Eligible · CSV import', { exact: true }).filter({ visible: true })).toBeVisible();
    await owner.screenshot({ path: 'test-results/list-membership-desktop.png', fullPage: true });
    await owner.setViewportSize({ width: 390, height: 844 });
    await expect(importedMember.getByText('Eligible · CSV import', { exact: true }).filter({ visible: true })).toBeVisible();
    await expect(owner.getByRole('checkbox', { name: 'Select page' })).not.toBeChecked();

    expect(await owner.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    await owner.screenshot({ path: 'test-results/list-membership-mobile.png', fullPage: true });
    await owner.setViewportSize({ width: 1440, height: 1000 });

    await owner.goto(base + '/identities');
    await owner.getByLabel('Sender name', { exact: true }).fill('Company News');
    await owner.getByLabel('Sender email', { exact: true }).fill('news@example.test');
    await owner.getByRole('button', { name: 'Save sender address', exact: true }).click();
    await expect(owner.getByText('news@example.test', { exact: true })).toBeVisible();
    await owner.goto(base + '/templates');
    await owner.getByRole('button', { name: 'Use template' }).first().click();
    await expect(owner.getByRole('textbox', { name: 'Email content' })).toBeVisible();
    await owner.getByRole('button', { name: 'Save template' }).click();
    await expect(owner.getByText('Template saved and sanitised.', { exact: true })).toBeVisible();
    await expect(owner.getByTitle('Template preview')).toBeVisible();
    await owner.goto(base + '/campaigns/create');
    await owner.getByLabel('Campaign name', { exact: true }).fill('E2E company campaign');
    await owner.getByLabel('Subject', { exact: true }).fill('A useful company update');
    await owner.getByLabel('Audience', { exact: true }).selectOption({ label: 'Company newsletter' });
    await expect(owner.getByText('3 contacts · 3 eligible · 0 excluded')).toBeVisible();
    await owner.getByRole('button', { name: 'Save campaign draft', exact: true }).click();
    await expect(owner).toHaveURL(/campaigns\/\d+$/);
    await expect(owner.getByTitle('Campaign preview')).toBeVisible();
    await owner.getByRole('button', { name: 'Mobile preview' }).click();
    await owner.screenshot({ path: 'test-results/company-campaign-preview.png', fullPage: true, animations: 'disabled' });
    await owner.getByRole('button', { name: 'Mark ready' }).click();
    await expect(owner.getByText('Simulation · 3 eligible recipients', { exact: true })).toBeVisible();
    await owner.setViewportSize({ width: 390, height: 844 });
    expect(await owner.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    await owner.getByRole('button', { name: 'Schedule simulation', exact: true }).click();
    await owner.setViewportSize({ width: 1440, height: 1000 });
    await expect(owner.getByText(/Completed · Simulation/)).toBeVisible({ timeout: 90000 });
    await expect(owner.getByText('Simulated accepted', { exact: false }).first()).toBeVisible();
    await owner.screenshot({ path: 'test-results/company-campaign-results.png', fullPage: true, animations: 'disabled' });
    await owner.goto(base + '/reports');
    await expect(owner.getByRole('link', { name: 'E2E company campaign' })).toBeVisible();
    await expect(owner.getByRole('cell', { name: 'Completed', exact: true })).toBeVisible();
    const campaignLink = await owner.getByRole('link', { name: 'E2E company campaign' }).getAttribute('href');
    const response = await admin.goto(campaignLink!);
    expect(response?.status()).toBe(403);
    await owner.goto(campaignLink!);
    await owner.getByLabel('Test recipient').fill(`success-${stamp}@example.test`);
    await owner.getByRole('button', { name: 'Create test draft', exact: true }).click();
    await expect(owner.getByRole('heading', { name: 'TEST — E2E company campaign', exact: true })).toBeVisible();
    await expect(owner.getByText('1 contacts · 1 eligible · 0 excluded', { exact: true })).toBeVisible();
    await owner.getByRole('button', { name: /Notifications/ }).click();
    const completionNotice = owner.getByRole('menuitem').filter({ hasText: 'Simulation complete' });
    await expect(completionNotice).toBeVisible();
    await completionNotice.click();
    await expect(owner).toHaveURL(/campaigns\/\d+\/analytics/);
    expect(errors).toEqual([]);
    await adminContext.close();
    await ownerContext.close();
    await teamContext.close();
});
