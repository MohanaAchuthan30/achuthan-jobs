## 0.17.0 — Native Elementor Job Listings Widget

- Register a native Elementor Free widget in the Achuthan Jobs category, usable inside any section/container.
- Four layouts plus responsive columns, job filters and display controls.
- Elementor Style tab supports card/hover backgrounds, featured card colour, accent, text, gap, radius and padding.
- Extended live AJAX rendering retains the selected widget options; prevents duplicate Careers fallback.
- Existing job post type, job URLs, applications and shortcodes are backward compatible.
- Added Elementor widget mock tests, updated starter template and setup checklist.

# Changelog

## 0.16.0 — Email customisation, HR attachments and eligibility improvements
- Configurable plain-text application acknowledgement subject/body per job, including placeholders for candidate, title, job ID, application ID and employer. Independent messages for multi-job submissions.
- Per-installation From email and sender display name settings. SMTP configuration still managed by the hosting site.
- HR email composer supports up to three files (PDF/DOCX/JPG/PNG, 3 MB each) using non-persistent secure temporary copies.
- Extended per-question eligibility rules: numeric intervals, date intervals, case-insensitive objective text phrases, and any/all multi-select requirements.
- Added HR applicant-list filter by eligibility flag. No automatic rejection.
- Persistent, capability-limited GitHub Release update notice linking to WordPress core updater; optional admin release cache refresh handler.
- Retained CPT ID, database table schema, job slugs, Elementor shortcode and existing data.
- Local PHP/JS lint and mocked regression tests. Live WordPress, SMTP, real upload and updater tests pending.

## 0.15.0
- Stop overriding the native `/careers/` page so Elementor content can be edited and rendered; append a temporary live list until an Elementor shortcode widget is placed.
- Add `[achuthan_jobs_list]` shortcode for live jobs with optional heading/filters.
- Maintain display options across AJAX refresh and handle Elementor shortcode rerendering.
- Add editable Elementor starter template and setup instructions.
- Preserve job post URLs, database, candidate records, and GitHub updater.


## 0.14.0 — GitHub public Release auto-update support

- Added WordPress `Update URI` and GitHub Releases update discovery.
- Added background auto-update integration scoped exclusively to Achuthan Jobs.
- Pinned package source to official GitHub Release asset `achuthan-jobs.zip`.
- Added SHA-256 check against GitHub asset digest before installing.
- Admin setting allows enabling/disabling auto-updates.
- GitHub Actions workflow builds and publishes a versioned release when a matching tag is pushed.
- Preserved existing plugin folder, job URLs, application database and HR functions.

## 0.13.0 — Job eligibility checks
- Per-job experience screening and configurable qualifying answers.
