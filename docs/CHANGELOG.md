# Changelog

## 0.13.0 — 2026-10-31

- Added structured experience thresholds per job (such as 2 years), qualifying answers to choice questions, and minimum numeric screening questions.
- Applicants to multiple jobs provide job-specific relevant experience; outcomes are saved separately per application.
- HR sees human-review eligibility flags and specific reasons in the Applications table, recent activity, and candidate details.
- Added authenticated, nonce-protected **Re-evaluate** operation with audit logging when job requirements change; existing applications remain **Not yet assessed** until manually checked.
- No auto rejection, hiring-stage changes, candidate scoring from resume PDFs, or decisions based on personal/demographic data.
- Public careers copy, employer name, candidate consent, email wording and SEO organisation now use the configured employer name or the current WordPress site title.
- Made location text and schema country/timezone independent of Bengaluru/India; works with WordPress shortcode on other sites. Internal identifiers remain for compatibility.


## 0.12.0 — 2026-10-30

- Rebranded the plugin author and all user-facing plugin text to **Achuthan**.
- Updated applicant emails, Careers and native job headings, HR Dashboard and recruitment privacy tool labels.
- Renamed the visible recruiter role label while preserving user role IDs and all database keys.
- New installations default notification email to the WordPress administrator address; existing settings remain unchanged.
- Kept existing data, job permalinks, shortcodes, and application workflows.


## 0.11.0 — 2026-10-30

- Canonical job routes: `/careers/{slug}/`, with legacy redirect.
- Frisk/Elementor-resistant checkboxes and radios for custom questions.
- Default dynamic `/careers/` page ownership with shortcode opt-out.

## 0.10.0

- Admin preview for private PDFs/images and application deletion controls.

## 0.9.0

- Multi-job application, 3 MiB file cap, friendly Job IDs and bulk ZIP export.

## 0.8.0

- Job-specific custom questions, answer options, file questions and ratings.

## 0.6.0

- Expanded main plugin HR dashboard and candidate tracking.

## Private repository preparation

- GitHub-only private source layout, CI checks and release packaging scripts.
- Updated plugin header to a proprietary/private notice for this source
  bundle; **prior GPL grants, if any, remain unaffected** (see LICENSE.md).
