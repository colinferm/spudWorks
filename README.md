# spudWorks Magazine

A custom WordPress theme for [spudWorks](https://www.spudworks.com/), a dark editorial web magazine covering fiction, miniature painting, and Necromunda/40k game recaps. It uses a custom responsive layout, self-hosted Space Grotesk and Fraunces type, and a restrained black-and-yellow hazard-stripe motif.

The theme lives at [`src/wp-content/themes/spudworks-mag/`](src/wp-content/themes/spudworks-mag/). It needs WordPress 6.0+ and PHP 8.0+, and it is multisite-safe throughout.

---

## Content display

### Home page

- **Featured hero.** The newest published sticky post stands alone, full width, at the top.
- **Pinned row.** A strip of three pieces sits under the hero. It is filled from the next-newest sticky posts and backfilled with the latest posts when fewer than three are pinned. A sticky post scheduled for later reserves its spot and appears when it goes live.
- **Mixed grid.** About a dozen recent pieces follow, in cards of varied shapes.

### Post templates

Each post picks a layout in its editor meta box:

| Template | Use |
| --- | --- |
| **Showcase** (default) | Content with a widgetized sidebar. Suited to painting and hobby posts with galleries. |
| **Default** | A single reading column at a 720px measure. |
| **Fiction** | A distraction-free reading layout with full-bleed images. No comments. |

Pages can also use a **Landing Page** template. It builds an evergreen topic hub in the Showcase layout, but the sidebar belongs to that page alone: two curated image galleries and a hand-picked, drag-to-reorder list of related articles. Editors fill these with the WordPress media picker and a live post search.

### Reading experience

- **Lightbox** for content and sidebar galleries, with keyboard, button, and swipe navigation and swipe-down to dismiss. It shows only captions that were written on purpose (the WordPress Caption field), never auto-filled alt text.
- **Attachment pages** with prev/next through the parent post's images, plus arrow-key and swipe navigation.
- **Themed separators.** A plain `<hr>` renders as a hazard-stripe divider, and `<hr class="orbit">` shows a small animated gas giant with an orbiting moon. Both respect `prefers-reduced-motion`.
- **Left-to-right "wipe" hover animations** on tag chips, headlines, and in-content links.
- **"Keep Reading"** related stories at the end of each post, and an author box.
- **Comment-count badges** on article datelines and on every card, so readers can see which pieces people are talking about.

### SEO and structured data

- JSON-LD schema for articles, the home page item list, and **reviews**. Posts in the Reviews category emit schema.org `Review` markup, and one post can hold **several reviews** of similar items, each with its own name, type, and 0.5–5 star rating. Unrated extra reviews are left out so the markup stays valid for Google rich results.
- Meta description, canonical, Open Graph, and Twitter/X card tags on every page.

---

## Shortcodes

All shortcodes are documented in a **Help tab** on the post edit screen and have a **Quicktags button** in the classic editor.

| Shortcode | What it does |
| --- | --- |
| `[multipart]` | Prev/next navigation for serialized posts. Linking by post ID means each side appears on its own once its target publishes, so a whole series can be wired up ahead of time. |
| `[author-note]` | A styled "A note from the author" aside. |
| `[featured-comment]` | Pulls an approved reader comment (with avatar) into the article as a quoted callout. |
| `[notice level="info\|warn\|danger"]` | A full-width callout with an icon and accent color set by its level. |
| `[pullquote]` | Sets apart a line from the article itself. It can be centered as a wide breakout or floated left/right. |
| `[aside]` | A small, skippable side note that floats beside the text. |
| `[toc]` | Builds a table of contents from the post's H2/H3 headings and adds matching anchor IDs to them. |
| `[archive-feature]` | A large graphical link card for hand-built archive pages. |
| `[necro-card]` / `[necro-weapon]` | A Necromunda fighter stat card with a nested weapon table. It comes in `full`, `half`, and compact `min` sizes and switches columns for the N23 or N26 ruleset (`ver="N23\|N26"`). Weapons inherit the card's ruleset. Cards in a row group together automatically, resize with container queries, and use accessible table/list markup. |

---

## Widgets

- **Widget areas:** a Showcase sidebar and three footer columns.
- **Featured Post widget.** Shows one chosen post as a card, either image-over-text or text-over-image. The widget form has a live AJAX search that matches post titles *and* tags.
- **Search.** Both the core Search block and the legacy Search widget are restyled as the theme's hazard-stripe search box.

---

## Comments and moderation

Comments appear on Showcase and Default posts. They are left off fiction and pages.

- **Guests can comment, but name and email are required.**
- **Honeypot anti-spam with no CAPTCHA.** Hidden decoy fields catch form-filling bots, and a JavaScript render timestamp catches submissions sent less than 60 seconds after the page loaded. Flagged comments go to the Spam queue instead of being rejected, so a moderator can still rescue a false positive.
- **Commenter emails** in branded HTML and plain-text versions:
  - *Held:* tells the commenter their comment is awaiting moderation.
  - *Approved:* tells them it's live.
  - *Reply:* sent only if they ticked "Email me when someone replies."
  - Site staff never receive these, but readers do get emailed when staff reply to them.
  - One-click unsubscribe with a signed token and RFC 8058 `List-Unsubscribe` headers.
- **"Send Approved Email" bulk action** in Comments, to send approved emails for older comments. Comments that were already emailed are skipped.
- **Email preview route** so admins can check the email designs without sending anything.

---

## S3 media offload

The theme can offload all uploaded media to Amazon S3 (or any S3-compatible store) and serve it from a CDN.

- **Credentials** are wp-config constants only. The bucket and an optional CDN hostname (CNAME) are set in **Settings → Media**.
- **Uploads are pushed right away**, so the editor already gets bucket URLs. After the request finishes, a cleanup pass removes orphaned remote files and deletes the local copies.
- **Editing still works.** The image editor and Regenerate Thumbnails pull originals back from S3 when needed, then push the results again.
- **Filename collisions are prevented** by checking the bucket listing, because local copies no longer exist to compare against.
- **Changing the CNAME** rewrites existing post content to the new hostname in a single pass.
- **WP-CLI:** `wp sw images sync` uploads existing media in bulk. It lists the bucket once instead of checking each file, and it refuses to overwrite a remote file whose contents differ. `wp sw images restore` brings files back.

The AWS SDK is installed with Composer. Production deploys need `composer install --no-dev` in the theme directory.

---

## Editorial tools

Admin-side features that support the publishing workflow:

- **Per-post To-Do lists.** A checklist meta box on each post, auto-filled with a starting set of tasks. Items can be edited in place, reordered by dragging, and tagged with `#tags`, where `#low`/`#med`/`#high` show as priority colors. The lists also appear on a **Posts → To Do** page and a dashboard widget, ordered by intended publish date.
- **Private calendar feed.** An ICS subscription URL protected by a secret token, for Apple Calendar and similar apps. It shows upcoming drafts and scheduled posts, includes their open to-dos, and sets a reminder the evening before.
- **Link search for unpublished posts.** The classic editor's Insert Link dialog also finds scheduled and draft posts. A daily cron job then swaps any `?p=ID` links for pretty permalinks once their targets publish.

---

## Building

SCSS is compiled with Dart Sass through gulp ([`build/`](build/)). The local environment runs in Docker (`docker-compose.yaml`) and includes Mailpit for catching outgoing email and RustFS as an S3 stand-in. The `Version:` header in `style.css` doubles as the asset cache-buster.

## License

Copyright © Colin Andrew Ferm. Licensed under the [GNU General Public License v3.0](LICENSE).
