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
    await owner.getByLabel('Email address', { exact: true }).fill(teamEmail);
    await owner.getByRole('button', { name: 'Create invitation', exact: true }).click();
    const invitation = await owner.getByLabel('Local acceptance link · share securely').inputValue();
    await team.goto(invitation);
    await team.getByRole('button', { name: 'Accept invitation' }).click();
    await expect(team).toHaveURL(/dashboard/);
    await owner.goto(base);
    await expect(owner.getByText(teamEmail, { exact: true })).toBeVisible();
    await owner.goto(base + '/imports');
    await owner
        .getByLabel('CSV file')
        .setInputFiles({
            name: 'company-contacts.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(
                `email,first_name\nsuccess-${stamp}@example.test,Alex\ntemporary-${stamp}@example.test,Taylor\npermanent-${stamp}@example.test,Jordan\ninvalid,Bad\n`,
            ),
        });
    await owner.getByRole('button', { name: 'Upload and preview' }).click();
    await expect(owner).toHaveURL(/imports\/\d+/);
    await owner.getByRole('button', { name: 'Start background import' }).click();
    await expect(owner.getByText(/CSV import · completed/)).toBeVisible({ timeout: 30000 });
    await expect(owner.getByText('Row 5: rejected', { exact: false })).toBeVisible();
    for (const local of ['success', 'temporary', 'permanent']) {
        await owner.goto(base + '/contacts');
        await owner.getByRole('link', { name: `${local}-${stamp}@example.test`, exact: true }).click();
        await owner.getByLabel('Evidence', { exact: true }).fill('Explicit email consent recorded for this demonstration company');
        await owner.getByLabel('Method', { exact: true }).fill('Demonstration signup form');
        await owner.getByLabel('Evidence timestamp').fill(new Date(Date.now() - 86400000).toISOString().slice(0, 16));
        await owner.getByRole('button', { name: 'Record evidence' }).click();
        await expect(owner.getByText('Eligibility evidence recorded.', { exact: true })).toBeVisible();
    }
    await owner.goto(base + '/audiences');
    await owner.getByLabel('List name', { exact: true }).fill('Company newsletter');
    await owner.getByRole('button', { name: 'Create list', exact: true }).click();
    await owner.getByRole('link', { name: /Company newsletter/ }).click();
    await owner.getByRole('checkbox', { name: 'Select page', exact: true }).check();
    await owner.getByRole('button', { name: 'Add selected', exact: true }).click();
    await expect(owner.getByText(/3 current eligible recipients/)).toBeVisible();
    await owner.goto(base + '/identities');
    await owner.getByLabel('Sender name', { exact: true }).fill('Company News');
    await owner.getByLabel('Sender email', { exact: true }).fill('news@example.test');
    await owner.getByRole('button', { name: 'Save unverified identity' }).click();
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
    await owner.getByRole('button', { name: 'Schedule simulation', exact: true }).click();
    await expect(owner.getByText(/COMPLETED · Local simulation only/)).toBeVisible({ timeout: 90000 });
    await expect(owner.getByText('Simulated accepted', { exact: false }).first()).toBeVisible();
    await owner.screenshot({ path: 'test-results/company-campaign-results.png', fullPage: true, animations: 'disabled' });
    await owner.goto(base + '/reports');
    await expect(owner.getByRole('link', { name: 'E2E company campaign' })).toBeVisible();
    await expect(owner.getByRole('cell', { name: 'completed', exact: true })).toBeVisible();
    const campaignLink = await owner.getByRole('link', { name: 'E2E company campaign' }).getAttribute('href');
    const response = await admin.goto(campaignLink!);
    expect(response?.status()).toBe(403);
    expect(errors).toEqual([]);
    await adminContext.close();
    await ownerContext.close();
    await teamContext.close();
});
