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
