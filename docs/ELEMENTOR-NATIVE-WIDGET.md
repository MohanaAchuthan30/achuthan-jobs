# Native Elementor Widget — Achuthan Jobs v0.17.0

Achuthan Jobs now registers a drag-and-drop Elementor widget under **Achuthan Jobs** and **General**. Elementor Free is sufficient. The existing `[achuthan_jobs_list]` shortcode and its previously saved designs still work.

## Install and use

1. Back up WordPress and the database. Update the **existing** `achuthan-jobs` plugin on staging. Do not install it as a separate plugin.
2. Check Elementor is active; go to **Pages → Careers → Edit with Elementor** (or edit any page).
3. Add a new **Container/Section** in Elementor, then search the Widgets panel for **Achuthan Jobs — Job Listings**. Drag it inside the section.
4. **Content → Job listings:** Choose a style: **Cards grid**, **Full-width list**, **Compact tiles**, or **Editorial cards**. Set Desktop/Tablet/Mobile columns independently (list mode uses full width). Choose job limit (0 = all), sort direction, department, employment type, search filters, heading, and visibility of Job IDs, metadata, and description excerpts.
5. **Style → Card appearance:** Select card and hover backgrounds, featured card background, accent, title colour, card gaps, corner radius and card padding. The typography defaults to Poppins.
6. Publish/update the Elementor page. From **Achuthan Jobs → Manage Jobs**, publish a test vacancy and confirm it appears. Close the job and confirm it disappears. Listings refresh from the existing no-cache AJAX endpoint while a page is open; caching may require an initial hard refresh.

**No separate job data entry in Elementor:** Vacancy details and applications remain in Achuthan Jobs. Elementor changes *presentation*, not the source of truth.

## Upgrade notes

- The standard WordPress `/careers/` Page is still editable, and any previous shortcode layouts keep working.
- After a **native** Achuthan Jobs widget is saved on `/careers/`, the temporary appended listing from earlier releases will not appear, preventing duplicate jobs.
- Job URLs remain `/careers/{job-slug}/`; the post type remains `bbs_job` for upgrade compatibility. Applicant data, forms, encryption keys and HR dashboard identifiers are unchanged.
- If Elementor is disabled, the careers shortcodes still work and the native widget simply is not registered. No Elementor Pro dependency is introduced.
- The widget can be used more than once with different departments, layouts and filters. It does not add another application form to the listing page; applicants open the existing individual job page to apply.
- Appearance controls are scoped to the widget; the listings use escaped published post data and validated public AJAX parameters. The AJAX endpoint does not expose candidate records.
- The editor preview needs Elementor's standard preview/refresh mechanism and frontend assets. If the layout appears blank or the style does not change, clear Elementor CSS cache and site/CDN cache, then test on staging.

## Staging acceptance checklist

- [ ] Native widget appears in Elementor Widgets panel without Elementor Pro.
- [ ] Four layouts render in editor and on public page.
- [ ] Columns change responsively on desktop/tablet/mobile.
- [ ] Search, department filter, and employment type filter work independently for two widgets on one page.
- [ ] Job limit, sort direction, metadata visibility and custom section text persist after publish and live refresh.
- [ ] Elementor colour, gap, radius and padding controls apply after live refresh.
- [ ] Newly published/closed jobs update the relevant widgets without editing Elementor.
- [ ] The Careers page has no extra appended copy of the listings.
- [ ] Individual job URL, consent, 3 MB file limit, custom questions, application submission, and HR screening still work.
- [ ] Disable Elementor briefly on staging and ensure the plugin does not fatal; existing shortcodes remain available.
- [ ] Re-test with your active theme, caching plugins, mobile browsers, and PHP/WordPress/Elementor versions.

**Testing scope:** Automated syntax and mocked API assertions run locally. Real Elementor, WordPress database, WP-Cron, SMTP and application security tests require a staging WordPress installation. This is not a production security certification.
