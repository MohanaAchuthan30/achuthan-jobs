# Achuthan Jobs

**Private repository • Achuthan • WordPress plugin • v0.12.0 (staging)**

> This is a **private source repository**, not an open-source release or a
> production security certification. Keep GitHub repository visibility set to
> **Private**. The current plugin needs WordPress staging tests before accepting
> real applicant personal data.

Achuthan Jobs manages WordPress job postings, applications, candidate files,
interviews, recruiter communications, job-specific questions, and the public
Careers page at `/careers/`. All public and HR interface labels now use **Achuthan**; existing database and URL identifiers are preserved.

## Project structure

```text
achuthan-jobs-private-github/
├── plugin/
│   └── achuthan-jobs/         # Installable plugin source, PHP, CSS, JS
├── docs/
│   ├── GITHUB-SETUP.md        # Private repo creation and push instructions
│   ├── INSTALLATION.md        # WordPress staging upgrade procedure
│   ├── TESTING.md             # Acceptance tests and known limits
│   └── CHANGELOG.md
├── scripts/
│   ├── verify.sh              # PHP lint, JS syntax, smoke checks
│   └── build-plugin.sh        # Create WordPress installable ZIP in dist/
├── .github/workflows/ci.yml  # Private lint/package CI; no deployment
├── .gitignore
├── LICENSE.md                 # Proprietary/private notice and caveat
├── SECURITY.md               # Private vulnerability reporting guidance
└── README.md
```

## Requirements

- WordPress 6.2+ and PHP 8.0+ (not tested against every version)
- Recommended PHP extensions: `openssl`, `fileinfo`, `zip` (ZIP needed for bulk exports)
- Poppins loaded via site/frontend; no font file is committed here
- An existing **WordPress page** with slug `/careers/`
- WordPress HTTPS, properly configured SMTP, server caching exclusions and backups

## Quick start

1. Read [private GitHub setup](docs/GITHUB-SETUP.md).
2. Push this repository into **a new empty GitHub repository set to Private**.
3. Run `bash scripts/verify.sh` and `bash scripts/build-plugin.sh` locally.
4. Upload the ZIP from `dist/` to a **staging** WordPress instance.
5. Save **Settings → Permalinks** once and test public job URLs.
6. Test the application, custom questions, file previews, deletion, and emails
   on staging before using it for real applicants.

## WordPress integration

- Main private admin screen: **Achuthan Jobs → HR Dashboard**.
- Native job post type: `bbs_job`.
- Public listing: `https://bigboxstudio.in/careers/`.
- Individual jobs: `/careers/{job-slug}/`.
- Legacy `/careers/job/{job-slug}/` should redirect after rewrite rules refresh.
- The plugin controls `/careers/` by default. To use an Elementor-owned layout,
  disable automatic page control in plugin Settings and insert `[achuthan_jobs]`
  via Elementor's Shortcode widget.

## Included recruitment features

- Create/edit/archive job listings, stable friendly Job IDs and bulk actions.
- Job-specific screening questions, including multiple-select questions and
  optional PDF/image attachments (3 MiB per file).
- Candidate application to up to three positions in one submission.
- Private HR dashboard for applicant status, notes, interviews, email actions,
  private previews, individual and ZIP bulk document export, and deletion.
- Live Careers listings with background refresh; test CDN/plugin caching.

## Limitations

- Existing `tests/security-smoke.php` is a **mocked** test harness, not
  a real WordPress integration or penetration test.
- There is no mandated malware scanner. MIME/structure checks and encryption
  are not equivalent to malware scanning.
- Emails depend on WordPress mail configuration and delivery reputation.
- Protect applicant PII: never put production resumes, candidate CSV exports,
  backups, database dumps, `.env` files, salts, or credentials into GitHub.
- Verify license obligations before distributing the plugin outside the team.

See [LICENSE.md](LICENSE.md), [SECURITY.md](SECURITY.md) and [testing](docs/TESTING.md).

## Branding and backward compatibility (v0.12.0)

- Plugin header, HR dashboard, job pages, Careers copy, application consent, email subjects and recruitment privacy tools now say **Achuthan** instead of the previous brand.
- Existing role ID `bbs_recruiter`, job post type `bbs_job`, `bbs_` database tables, options, CSS classes, actions and legacy shortcode are **not renamed**: doing so would disconnect existing users, jobs and applications.
- New installations use the WordPress administrator email for recruitment notifications. **Existing email settings are never overwritten on upgrade.** Check the notification address in Achuthan Jobs → Settings.
- If your legal employer or data controller has a name different from Achuthan, update the job posting organisation and privacy notice to accurately describe it before recruiting.
- Historical copyright and prior GPL rights remain governed by LICENSE.md and are not silently changed by rebranding.
