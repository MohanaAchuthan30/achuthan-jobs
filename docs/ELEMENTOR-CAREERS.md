# Achuthan Jobs 0.15.0 — Elementor-editable Careers page

## What changed

- `/careers/` is a normal WordPress page again. The plugin no longer overrides its template or hides previously saved Elementor design. Existing job posts and application records remain in place.
- **Only the live job list** needs a Shortcode widget. Everything else—hero, images, layout, text, colour, spacing, CTA and footer—is editable with native Elementor widgets.
- Elementor Free is sufficient; Elementor Pro is not needed. This also works in the WordPress Shortcode block without Elementor.

## Steps on an existing website

1. Back up the site and database; upgrade the plugin on staging.
2. In WordPress Pages, open the Page with slug `careers` and click **Edit with Elementor**. The original page content becomes visible again. A temporary live jobs list is appended at the bottom until you add the Shortcode widget.
3. Remove any static/mock vacancies from the previous design to avoid duplicate or stale results.
4. Insert Elementor's native **Shortcode** widget where jobs belong. Enter `[achuthan_jobs_list]`.
5. To use your own Elementor heading above the list, enter `[achuthan_jobs_list heading="no" filters="yes"]`. Use `filters="no"` only if you don't want the plugin's search and department filters.
6. Save and test by publishing and closing a dummy job in Achuthan Jobs → Manage Jobs. Jobs refresh on page visits via an uncached AJAX request and periodically while a tab is open. If older content remains, clear server and CDN cache.
7. The temporary jobs list is automatically suppressed when the widget is saved in Elementor, so jobs only appear in your chosen location.
8. If the WordPress Careers page doesn't exist, create a Page named `Careers` with slug `careers` and edit it in Elementor.

**Full-page legacy shortcode:** `[achuthan_jobs]` retains the old entire landing-page design if you prefer.

**Job detail pages** remain managed by Achuthan Jobs (`/careers/{job-slug}/`) and are not Elementor-designed by this update.

## Optional importable Elementor starter template

Import the sibling `elementor/achuthan-careers-page.json` under **Templates → Saved Templates → Import Templates**, then insert it on your Careers page. It uses native sections, headings, text and buttons, plus the one dynamic job-list shortcode widget. Review and customise links, copy and spacing before publishing.

## Compatibility and limitations

Shortcode widget works in modern Elementor Free and WordPress; plugin assets are enqueued for Careers pages and when the shortcode renders. Widget lifecycle hooks aid previews in the live editor. Versions earlier than the declared WordPress/PHP requirements have not been tested. Real hosting/staging testing is required before use with actual candidate information.
