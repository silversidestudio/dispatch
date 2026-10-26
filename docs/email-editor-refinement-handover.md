# Email editor, templates and preview refinement

26 October 2026. Branch: `feature/email-editor-refinement`, based on clean Phase 1C `eac6947`. Dispatch was not renamed. No deployment, push, live email, DNS or provider configuration change occurred. Existing users, templates and campaigns were not reset or reseeded. Local preview login and the bundled image endpoint were checked: both returned HTTP 200. Horizon was restarted to load the updated code; live delivery gates were not changed.

## Delivered

- Lucide icon toolbar with active/disabled formatting, typography dropdown, bold/italic, bullet/numbered lists, alignment, link/image/button/divider insertion, personalisation menu, undo/redo and accessible tooltips. Less common tools move into More on narrow screens. Controls retain keyboard focus/selection and use Radix/shadcn menus/dialogs.
- Light 600px email canvas within a subtle theme-aware workspace; consistent typography, lists, spacing, links, selection outlines and empty guidance. A minimum-width fix in SidebarInset/Workspace prevents intrinsic-width email previews from expanding the phone page.
- Selectable CTA blocks with label, validated HTTPS destination, background/text colour, alignment and corner radius. Server presentation uses table-cell background/padding for a classic Outlook fallback. The padded cell can have square corners in clients without radius support; actual Outlook/Gmail verification remains outstanding.
- HTTPS image dialog with preview, alternative text, alignment, responsive width presets, optional destination and removal. Existing small logos retain their dimensions. No upload/media manager was introduced; URL-based images remain the supported workflow.
- Five original **Studio v2** starter designs: business announcement, newsletter, product/service promotion, event invitation and simple professional email. Each includes sender/address/unsubscribe tokens and a plain-text alternative with link destinations. An original geometric PNG hero is bundled; there are no third-party logos or stock assets. Starter creation always adds a new design and never updates existing modified templates.
- Visual library cards with sandboxed generated thumbnails, categories, descriptions, create/use/preview/edit/duplicate actions and an empty state. Preview dialogs support desktop/mobile/plain text.
- Shared preview component for saved templates and campaigns, with sender, email, subject, preview text, sample recipient values and saved-content guidance. Mobile mode creates a narrow iframe viewport (approximately 375px, constrained to fit smaller screens), exercising responsive CSS rather than scaling desktop content. Thumbnails intentionally scale for browsing; full previews do not.
- Lightweight organisation email branding: existing company name/address, optional HTTPS logo, primary/secondary/button colours and footer information. Owner/admin settings affect newly created starters only. Existing templates and campaign snapshots remain unchanged.

## Implementation locations

Frontend:

- `resources/js/components/email-editor.tsx`: editor orchestration, icon toolbar and insertion/property dialogs.
- `email-nodes.ts`: selectable button/image nodes, table/section/inline-style preservation, safe URL validation.
- `email-preview.tsx`: shared sandboxed viewport/plain-text presentation and sender metadata.
- `email-branding.tsx`: small organisation branding form.
- Templates/library, template editor, campaign editor/detail and organisation settings pages; scoped canvas styles in `resources/css/app.css`.
- `ui/sidebar.tsx` and `workspace.tsx`: minimal flex min-width corrections, without navigation redesign.

Backend:

- `EmailContentService`: HTMLPurifier allowlist, established token syntax, safe plain-text conversion and shared renderer entry point.
- `EmailMarkup`: DOM-based presentation, responsive image/table constraints, inline typography, CTA table fallback, escaped personalisation and URL validation. No browser Tailwind classes are required by outbound HTML.
- `EmailStarterTemplates`: original versioned catalogue and creation-time branding.
- `resources/views/email/document.blade.php`: document structure, responsive media CSS, preview text, fallback fonts and conditional MSO width wrapper.
- `ContentController`: tenant-scoped starter previews, branding authorisation and existing template actions. `CampaignController`/`CampaignService`: accurate snapshot-based preview/sender metadata. `SendGridSubmissionService` only passes frozen preview text into the same renderer; delivery architecture and gates are unchanged.

Dependencies: explicit `@tiptap/core` and maintained `@tiptap/extension-table` added within the existing 3.x family. Tiptap, Laravel and the application stack were not replaced or upgraded to a new major version.

## Database and preservation

One additive migration: `2026_10_26_000002_add_email_branding_to_organisations.php`, nullable JSON `organisations.email_branding`. Applied with `php artisan migrate --force`; no destructive migration, reseed or password change.

Branding is copied into new starter markup at creation. It does not retroactively update saved designs. Tests compare existing template HTML and campaign snapshots before/after branding and new-template creation. The editor preserves table text, colspan/background, inline span colour and small-image dimensions through save/reload. Existing snapshots are never rewritten; rendering adds compatible presentation around their original content and respects explicit content styles.

The bundled image remains a relative same-origin asset in HTTP development so LAN previews do not reference the phone's localhost. With a production HTTPS APP_URL it becomes an absolute HTTPS URL suitable for outbound messages. Verify that this public asset is reachable on the authorised deployment before a pilot.

## Verification

Baseline: 106 PHP tests / 451 assertions passed before edits.

Final results:

| Command | Result |
|---|---|
| `php artisan test --compact` | 111 passed / 550 assertions |
| `DB_CONNECTION=mysql DB_DATABASE=dispatch_test php artisan test --compact` | 111 passed / 550 assertions; isolated test database only |
| `npx tsc --noEmit` |Passed |
| `npx eslint resources/js` |Passed |
| `npm run build` |Passed; Vite 6.4.5, 2100 modules |
| `vendor/bin/pint --test` |Passed |
| `git diff --check` |Passed |
| `npm audit --audit-level=low` | 0 vulnerabilities |
| `npx playwright test` |5 Chromium journeys passed,59.0s |
| `npx playwright test tests/browser/email-editor.spec.ts` |2 focused editor/legacy journeys passed, 10.4s, after final footer/asset adjustments |

`EmailEditorTest.php` verifies five designs, responsive markup, required footer, CTA styles, idempotent sanitisation, HTTPS images, malicious HTML/CSS/URL rejection, fallback/escaped personalisation, link-aware plain text, branding, duplication, tenant/role boundaries and immutable snapshots. Existing SendGrid, webhook, unsubscribe, reporting, auth and simulation tests remain included.

`email-editor.spec.ts` exercises icon commands, CTA editing/unsafe URL rejection, image insertion/unsafe URL rejection/alignment/removal, bold/italic/typography/lists/alignment, variables, undo/redo, saving/reloading/duplication, mobile and plain-text previews, CSS viewport reflow and page overflow at 320/390/768px. A second journey preserves an existing table, inline colour, colspan and 120px logo. Existing browser journeys remain operational.

Intermediate findings were corrected before final verification: sanitiser display/radius handling, URL-encoded unsubscribe tokens, intrinsic flex overflow, initial canonical HTML being reported as an unsaved edit, duplicate form state and a browser test capturing its URL before navigation finished. No final failed checks are being represented as passed.

## Screenshots

Captured from fake local demonstration data and visually reviewed. These are browser previews, not Gmail/Outlook screenshots.

- [Desktop editor](screenshots/email-editor-desktop.png)
- [Mobile editor](screenshots/email-editor-mobile.png)
- [Desktop email preview](screenshots/email-preview-desktop.png)
- [Mobile email preview](screenshots/email-preview-mobile.png)
- [Template library](screenshots/email-library-desktop.png)

## Known limits and client checks

1. Real Outlook/Gmail/iOS/Android email-client rendering has NOT been tested. Before an authorised pilot verify client-specific spacing, button click area, square-corner fallback, images-off/alt behaviour, dark-mode transformations, typography and responsive widths in actual received messages. Local Chromium success is not proof of email-client compatibility.
2. Images require public HTTPS hosting (except the bundled same-origin development asset). Remote images can make network requests; previews disclose this and use no-referrer sandboxing. Uploads, resizing/CDN/media management are deferred.
3. No drag-and-drop page builder, nested column designer or raw HTML editor was introduced. Mature Tiptap table support preserves existing layouts; this is still a focused rich-text editor. Not every arbitrary imported HTML/CSS feature is supported: the sanitiser intentionally restricts unsafe/unsupported content.
4. Plain text is stored separately and must be reviewed when HTML is edited. The provided starters include useful alternatives; HTML-only changes do not silently rewrite a user's manually edited plain text.
5. Template previews use sample people and demonstrate unsubscribe appearance without suppressing anyone. A real public unsubscribe/DKIM/provider pilot and the other Phase 1C launch gates remain outstanding in production-readiness.md.
6. Branding and company footer wording do not establish marketing consent or legal compliance. Eligibility, suppression and pre-flight checks remain authoritative.

## Reviewing locally

Open the network preview at `http://192.168.0.22:8013` on the same LAN. In a company workspace choose **Templates**, preview the Studio collection and use a design to create an editable copy. Select a button/image to reveal its edit/remove controls. Configure optional branding in Workspace settings. Platform administrators still need company membership for private content; this task did not grant implicit access or change any existing password.

Implementation commits: `9052b2e` (safe rendering/branding/starters) and `72107bb` (responsive editor/library/previews). Git commits for rendering, UI and this handover are local only. Obtain exact hashes with `git log -4 --oneline`; the final documentation commit cannot embed its own hash. Working-tree status is checked after the final commit.
