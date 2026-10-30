# Achuthan Jobs 0.12.0 — Branding-only upgrade

1. Back up the WordPress database and plugin directory; install on staging.
2. Replace the existing `achuthan-jobs` plugin, not a new renamed folder.
3. Verify WordPress → Plugins shows **Achuthan Jobs**, author **Achuthan**, version **0.12.0**.
4. Open the HR Dashboard, candidate email composer, `/careers/` and an open job. Verify the name Achuthan throughout the UI.
5. Existing jobs, applicants, Job IDs, canonical job URLs (`/careers/{slug}/`), CSV exports and candidate files should remain accessible.
6. Review the recruitment notification email address under Settings; the upgrade does **not** replace a previously saved recipient address.
7. If the actual employer/data controller differs from Achuthan, ensure the displayed brand, JobPosting schema and privacy policy identify the correct organisation.
8. Test an application with dummy applicant data and verify the confirmation email; real hosting/SMTP and Elementor checks remain required.

Legacy `bbs_` identifiers are internal compatibility keys, not displayed branding. Do not rename them without a database migration. This is not a new HR system and no data deletion is intended.
