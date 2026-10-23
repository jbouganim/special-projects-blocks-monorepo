## 0.9.0 (2026-10-05)

### Accessibility

- Mobile sidebar drawer: exposed as a modal dialog (`role="dialog"`, `aria-modal`, label) while open; focus moves to the Close button, the rest of the page is made `inert`, and focus returns to the trigger on close. The trigger now has `aria-controls`.
- Client-side navigation moves focus to the new page's `<h1>` (falls back to the content region) so screen readers announce the page change.
- All Guides: article card titles render as `<h3>` (was `<h5>`) so they nest under their `<h2>` category heading. Visual size is unchanged (set by `has-medium-font-size`).
- Breadcrumb: the mobile docs navigation trigger has a 44×44 tap target (was 20×20) with no visual change — padding offset by negative margins.

### Performance

- Sidebar: the category/article tree is cached in a transient (`a8csp_kb_docs_get_sidebar_tree()`) instead of being rebuilt on every request — ~760 queries / ~450ms down to ~15 queries / ~33ms per render. Per-page state (active item, expanded sections) is still applied at render time, so output is unchanged. The cache is invalidated when docs articles, categories, category assignments or category meta change; TTL filterable via `a8csp_kb_docs_sidebar_cache_ttl`.

### Fixes

- Client navigation only intercepts same-site KB Docs links. Links to other hosts, same-site pages outside the docs base, paths excluded via `a8csp_kb_docs_client_navigation_excluded_paths` (e.g. a standalone API reference at `/docs/api/`), and URLs whose fragment isn't a valid CSS id now load normally — fixes broken soft-nav renders, the `pushState` SecurityError on external links, and the `querySelector` SyntaxError on `#tag/...` fragments.
- `a8csp_kb_docs_get_primary_term_for_post()` only honours `_kb_primary_category` while that term is still assigned to the article; a stale value (after recategorising) falls back to the assigned term, so breadcrumbs and active sidebar state follow the real category.

### Improvements

- All Guides: trimmed article excerpts end with an ellipsis, matching the core Post Excerpt block on category archives. Cache key bumped so stale excerpts are rebuilt on deploy.
- All Guides: the template now stores small `perPage` values so the block editor previews a couple of sections instead of every category, with a `render_block_data` guard that restores the full limits on the front end.

## 0.8.0 (2026-09-02)

### New Features

- Breadcrumb: "Collapse on mobile" option (default on) shows only the first and current crumb below 960px, dropping intermediate categories instead of wrapping them.

### Improvements

- Breadcrumb: the trail never wraps to a second line — the current crumb truncates with an ellipsis when the title is too long.

## 0.7.0 (2026-08-26)

### New Features

- All Guides page support: cached server-rendered sections, `all-guides` docs view, breadcrumb label, and Terms Query short-circuit for large doc libraries.

## 0.6.0 (2026-08-12)

### Improvements

- Mobile sidebar chevrons toggle down/up (instead of right/down) below 960px.
- Suppress the mobile drawer transition while resizing across the breakpoint so the panel does not flash open.
- Keep the sidebar block full-width within its fluid desktop column (max 300px).

## 0.5.0 (2026-08-06)

### New Features

- Query Total variation: show remaining results beyond the current page (e.g. “3 more guides”), optionally linked to the Query Loop’s taxonomy archive; hidden when there are no remaining results.
- Docs home term-article Query Loops include child-term posts so category cards list guides across the category tree.
- Query Loop variations include_children now defaults to true.

## 0.4.0 (2026-08-06)

### Improvements
- Improved styles for KB Docs blocks.

## 0.3.0 (2026-07-17)

### New Features

- Sidebar accordion: expand/collapse nested terms without navigating; ancestors of the current term open by default.
- Mobile sidebar overlay below 960px (slide-in panel, backdrop, Browse header, Escape/close).
- Breadcrumb option to show a mobile sidebar overlay trigger button (default on).
- Categories block variation for KB Docs child terms (inherit parent from Term Template context, optional limit).
- Term Count variation: optional link to the term archive; rename suffix control to “Suffix label” with custom singular/plural labels (falls back to post type labels).
- Docs view markers (`data-kb-view`) on sidebar, breadcrumb, and content for template/CSS targeting.
- Improved Interactivity API client navigation for docs links (prefetch/navigate).

### Improvements

- Sidebar expandable terms use a button control (toggle only); leaf terms and articles still navigate.
- Term Count render preserves core wrapper supports classes when applying variation output.

## 0.2.0 (2026-06-16)

### New Features

- Add KB Docs sidebar block.
- Add KB Docs breadcrumb block.
- Add KB Docs content block.
- Add KB Docs table of contents block.
- Add KB Docs article block.
- Add KB Docs article body block.
- Add KB Docs article footer block.

## 0.1.0 (2026-04-23)

### New Features

- Initial scaffold for KB Docs block suite.
