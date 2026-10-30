# Achuthan Jobs v0.12.0 — Achuthan

**Staging build.** This ZIP upgrades the existing `achuthan-jobs` plugin. It keeps the existing job post type `bbs_job` and candidate tables, so it does not create a second recruitment system.

## Features retained from v0.9.0

- **Live `/careers/` job listings:** Publish, edit, close, reopen or trash jobs from WordPress; the existing `[achuthan_jobs]` or `[bbs_careers]` shortcode reflects the current published/open jobs. The page uses an uncached AJAX refresh when loaded and every 120 seconds while open. Ensure the `/careers/` page contains this shortcode in an Elementor Shortcode widget and isn't being replaced by an old static HTML list.
- **Job ID:** Jobs automatically receive a stable ID like `AJ-000123`, derived from their WordPress ID; HR may edit the ID to a unique code like `AJ-MKT-2026` in the job editor. The code appears on job cards, detailed jobs, applications, HR job tables, application notification emails and CSV exports. The numeric `job_id` column in the bulk CSV importer still means the internal WordPress post ID; do not put the friendly Job ID there.
- **Apply for multiple jobs:** From a job page, a candidate may apply to that position and **up to two additional open positions** in a single form submission. Additional role-specific questions load and display when each extra vacancy is selected. The applicant provides one resume and contact profile; HR receives a separate application per selected role, each with that role's answers and documents. Same-day duplicate applications for the same role/email are silently skipped; the person can still apply to other positions.
- **3 MB per file:** Required PDF resume and each optional/required PDF/JPG/PNG question attachment have an individual, server-enforced **3,145,728 byte (3 MiB)** limit. The builder supports up to 4 file questions per role. Allow for a form size of about 40 MB to cover 3 roles and all their custom files; see hosting limits below.
- **HR individual / bulk file downloads:** HR can download a resume or custom question attachment from the candidate detail page. On **Achuthan Jobs → Applications**, select applicant records, choose **Download selected files (ZIP)**, and click Apply. The ZIP contains per-job/per-applicant folders with the validated resume plus valid custom attachments. Bulk ZIP is limited to **20 selected applications** and **80 MiB uncompressed files** per request, and requires PHP `ZipArchive` extension.
- **No mandatory antivirus:** A scanner is *not required* for submitting or downloading. PDF magic bytes, PDF EOF/active content checks, MIME/extension checks, JPEG/PNG structure checks, upload size, AES-256-GCM private database storage, administrator/recruiter capability gates, POST-only downloads and CSRF nonces remain enforced. This **cannot guarantee uploads are malware-free**. Downloaded files should not be opened on uncontrolled/unprotected endpoints. Files previously recorded as `rejected` remain blocked.

## Install / upgrade

1. **Back up** your WordPress database and existing `achuthan-jobs` plugin; preferably test on Hostinger staging first.
2. WordPress → Plugins → Add New → Upload Plugin → select `achuthan-jobs-v0.12.0-wordpress-plugin.zip`, then **Replace current with uploaded**.
3. Go to **Achuthan Jobs → HR Dashboard**. `Manage Jobs` has bulk publishing and CSV imports, `Applications` has candidate details and bulk file exports. Each custom WordPress Job editor has Job ID, job fields, and job-specific questions.
4. By default **Achuthan Jobs owns the native WordPress page `/careers/`**, replacing its Elementor/static content with a live plugin listing. The WordPress page slug must be `careers`. To retain your Elementor design, disable the option in Achuthan Jobs → Settings and place `[achuthan_jobs]` in a Shortcode widget.
5. Verify one test job and that it appears automatically at `/careers/`. Close the job and confirm it disappears after refreshing the Careers page (or within ~2 minutes while the page remains open). Test the direct job URL `/careers/<slug>/` and that old `/careers/job/<slug>/` links redirect.
6. Submit a **fictional** candidate for two different open jobs, provide required per-role questions, and confirm two independent application records in HR. Test an email address already submitted to one role and a new role.
7. In the dashboard, test individual resumes and question files, and bulk download ZIP for multiple selected candidates. Confirm no private files appear in the WordPress media library or public uploads.
8. Test email sending, PHP upload limits, permissions (HR vs non-HR), privacy policy, data retention, cache/CDN and mobile UI. Only then deploy to production.

## Hosting requirements

- WordPress 6.2+, PHP 8.0+, HTTPS; PHP OpenSSL extension, Fileinfo extension, writable database with transaction-capable tables (InnoDB recommended).
- **PHP `ZipArchive` extension** for bulk ZIP exports; individual resume and question-file downloads work without it.
- Minimum `upload_max_filesize=3M`; `post_max_size` must exceed the total submission size. Recommended `post_max_size=48M` and `upload_max_filesize=8M` (the plugin still caps *each file* at 3 MiB), with adequate webserver request-body limits. `max_file_uploads` should be at least 13 for 3 selected jobs, each having four custom file questions plus the resume.
- Candidate email notifications use WordPress `wp_mail`; configure reliable SMTP/SPF/DKIM. Submission success does **not** prove mail delivery.
- WP Cron for retention; for reliability, schedule a real system cron to drive WordPress cron.

## Known limitations / security caveats

- **Not tested against every WordPress, Elementor or Frisk release.** It intentionally uses the public WordPress shortcode API, but test on your actual hosting stack.
- A controlled PHP WordPress/WPDB integration test and full browser E2E test with real uploads, ZIP exports and transactional rollback have not run here. Local PHP syntax, JS syntax and mocked smoke checks can pass without guaranteeing production reliability.
- Antivirus-free validation cannot detect all malicious PDF/image files; retain endpoint protection and never make private CVs public.
- Bulk ZIP uses PHP ZipArchive. The plugin will give a clear error if the extension is missing; ask your host to enable it.
- There is no candidate login/portal in this build. Three jobs per submission keeps form sizes manageable; candidates can submit more in later applications.
- Changing WordPress auth salts makes previously encrypted resumes inaccessible. Back up your encryption salts securely. Do not expose database backups to non-HR users.

## Previous 0.10.0 enhancements retained

- Private in-dashboard **Preview file** buttons for PDF resumes and PDF/JPEG/PNG screening-question attachments, displayed in an isolated modal viewer without a public file URL.
- Per-application **Delete** action directly in the applicant table; existing detail-page and bulk deletion remain.
- Atomic database deletion of one application and its encrypted attachments, without deleting separate applications by the same person.
- Existing bulk download, 3 MB file cap, multi-role applications, live `/careers/` shortcode and HR emailing retained.

See [UPGRADE-0.12.0.md](UPGRADE-0.12.0.md) for revised URLs, checkbox fixes, automatic Careers ownership and staging tests.

## New in 0.12.0

- Canonical direct job URLs: `/careers/{job-slug}/` with redirects from the old `/careers/job/{job-slug}/` structure. Save **Settings → Permalinks** once after upgrading.
- Multiple-choice questions display accessible real checkboxes and single-choice questions display radio controls even under Frisk/Elementor form CSS. Required multi-choice groups have client and server validation.
- New default: the plugin owns the `/careers/` WordPress page for reliable current listings. Turn this off in Settings if you prefer to maintain an Elementor layout with shortcode.
- Existing candidates and job data remain in the same tables and post type.

**Branding v0.12.0:** Display text is Achuthan. Legacy `bbs_` IDs remain for existing data; configured recruitment email settings are preserved.
