# Achuthan Jobs v0.17.0 — Elementor Native Widget

The plugin now includes **Achuthan Jobs — Job Listings** as a real Elementor widget, with four layouts and built-in Content/Style controls.

**Upgrade:** Back up files + database, replace the existing Achuthan Jobs ZIP on staging, then edit the Careers Page with Elementor and drag the Achuthan Jobs widget into a Section/Container. Edit jobs only under Achuthan Jobs → Manage Jobs; the widget is read-only and updates from live published job records.

**Compatibility:** The `[achuthan_jobs_list]` shortcode is unchanged. The plugin no longer appends a duplicate list after the native widget is saved. The internal `bbs_job` post type, candidate tables, application fields, permissions and old job URLs remain unchanged.

**Known requirement:** Elementor must be active to see the native widget. Elementor Free is supported. WordPress 6.2+ and PHP 8.0+ remain the declared minimums; test other versions on staging. The plugin can still run without Elementor using its shortcodes.

See `docs/ELEMENTOR-NATIVE-WIDGET.md` in the GitHub source repository for details and a staging acceptance checklist.
