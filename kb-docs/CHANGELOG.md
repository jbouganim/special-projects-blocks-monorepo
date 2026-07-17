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
