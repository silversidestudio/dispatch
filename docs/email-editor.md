# Email editor

The editor uses the free/open-source Tiptap React editor, StarterKit, Link and Image extensions. It supports headings, paragraphs, bold/italic, lists, horizontal sections, HTTPS links, HTTPS images, footer insertion and personalisation placeholders. No paid extension or hosted editor service is required.

Email templates are tenant-owned and can be created from three original starter designs, edited, previewed and duplicated. Campaigns can start from saved templates; plain text is separately editable. The editor is a practical rich-text foundation, not a full drag-and-drop responsive email builder. The Button control inserts a styled HTTPS link with email-safe colours and padding; advanced layouts can be added later.

Supported placeholders: first_name, last_name, email, sender_name, sender_address and unsubscribe_url, using double braces. The toolbar preserves the unsubscribe placeholder as a link. Campaign pre-flight requires unsubscribe placeholders in both HTML and text and a sender-address placeholder in HTML.

All template/campaign HTML is sanitised on the server with HTML Purifier. Scripts, handlers, frames, unsafe URI schemes and unsupported markup are removed. Styling is allowlisted. Preview rendering escapes substituted values and wraps content in a simple table-based email layout.

Desktop/mobile previews render saved content with example personalisation inside a sandboxed iframe, restricted CSP and no-referrer policy. They transmit no test email. Remote HTTPS images may load in the preview; operators should use appropriate image sources. The server does not download images, modify DNS or verify sender domains. Pixel-perfect compatibility with every email client is not claimed; future work should include cross-client email rendering tests and a broader layout system.

## Phase 1C

Five original inline/table-layout starters: business announcement, newsletter, product/service promotion, event invitation and simple professional email. Tiptap retains headings, paragraphs, emphasis, links, image/button/divider tools and adds text alignment through the maintained text-align extension. HTML sanitisation and plain-text content remain required. Personalisation escapes HTML; footer placeholders include sender address and unsubscribe. Desktop/mobile browser previews are tested, but Outlook/Gmail rendering has not been verified by actual messages.

Campaign detail now offers a single-recipient test preparation form. It accepts only an existing eligible, unsuppressed tenant contact and simulation/sandbox mode, creates a separate draft/audience with tracking off, and makes no provider request. The operator must review, mark ready and schedule separately. Live mode is not accepted by this preparation endpoint; an authorised live pilot uses a separately reviewed one-recipient live campaign with all normal gates. Sandbox validation contacts SendGrid only after explicit scheduling and configured gates; actual sandbox requests were not performed here.
