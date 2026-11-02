> **v0.17.0:** Native drag-and-drop Elementor widget with four layouts and responsive styling. See [the native Elementor widget guide](docs/ELEMENTOR-NATIVE-WIDGET.md).

> **v0.16.0:** Per-job application receipt emails, configurable sender, HR emails with controlled attachments, expanded eligibility screening, and a mandatory GitHub update notification. See [upgrade instructions](docs/UPGRADE-TO-0.16.0.md).

# Achuthan Jobs

**Achuthan • WordPress plugin • v0.17.0 (staging)**

> The repository is publicly visible, but a proprietary notice does not grant blanket redistribution rights. Earlier GPL-licensed releases may carry continuing rights and obligations. This is a staging build, not a production security certification. Test on WordPress staging before accepting real applicant personal data.

Achuthan Jobs manages WordPress job postings, applications, candidate files,
interviews, recruiter communications, job-specific questions, and the public
Careers page at `/careers/`. The **plugin publisher remains Achuthan**, while job postings, application consent, candidate emails, and SEO hiring organisation use the **employer name configured per WordPress website**. Existing database and URL identifiers are preserved.

## Project structure

```text
achuthan-jobs-repository/
├── plugin/
│   └── achuthan-jobs/         # Installable plugin source, PHP, CSS, JS
├── docs/
│   ├── GITHUB-SETUP.md        # Historical GitHub setup instructions
│   ├── INSTALLATION.md        # WordPress staging upgrade procedure
│   ├── TESTING.md             # Acceptance tests and known limits
│   └── CHANGELOG.md
├── scripts/
│   ├── verify.sh              # PHP lint, JS syntax, smoke checks
│   └── build-plugin.sh        # Create WordPress installable ZIP in dist/
├── .github/workflows/ci.yml  # Lint/package CI; no production deployment
├── .gitignore
├── LICENSE.md                 # Proprietary/private notice and caveat
├── SECURITY.md               # Security reporting guidance
└── README.md
```

## Requirements

- WordPress 6.2+ and PHP 8.0+ (not tested against every version)
- Recommended PHP extensions: `openssl`, `fileinfo`, `zip` (ZIP needed for bulk exports)
- Poppins loaded via site/frontend; no font file is committed here
- An existing **WordPress page** with slug `/careers/`
- WordPress HTTPS, properly configured SMTP, server caching exclusions and backups

## Quick start

1. Read [GitHub auto-update setup](docs/GITHUB-AUTO-UPDATES.md).
2. Publish the source at the configured GitHub repository and release versions using the tagged-release workflow.
3. Run `bash scripts/verify.sh` and `bash scripts/build-plugin.sh` locally.
4. Upload the ZIP from `dist/` to a **staging** WordPress instance.
5. Save **Settings → Permalinks** once and test public job URLs.
6. Test the application, custom questions, file previews, deletion, and emails
   on staging before using it for real applicants.

## WordPress integration

- Main private admin screen: **Achuthan Jobs → HR Dashboard**.
- Native job post type: `bbs_job`.
- Public listings: drag **Achuthan Jobs — Job Listings** into any Elementor section/container; choose Cards grid, List, Compact or Editorial with responsive style controls. The old `[achuthan_jobs_list]` shortcode still works, and `[achuthan_jobs]` provides a full ready-made page.
- Individual jobs: `/careers/{job-slug}/`.
- Legacy `/careers/job/{job-slug}/` should redirect after rewrite rules refresh.
- Elementor controls the `/careers/` page; add `[achuthan_jobs_list]` to render live vacancies.
  via Elementor's Shortcode widget.

## Included recruitment features

- Create/edit/archive job listings, stable friendly Job IDs and bulk actions.
- Job-specific screening questions, including multiple-select questions and
  optional PDF/image attachments (3 MiB per file).
- Candidate application to up to three positions in one submission.
- Per-job HR-configurable experience threshold and qualifying choice/number answers; persisted **Requirements not met**, **Meets stated requirements**, or **Needs HR review** flags; no automatic rejection.
- **Achuthan Jobs → Settings** configures employer organisation name and optional ISO country per site (independent of plugin publisher).
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

## Branding and backward compatibility (v0.14.0)

- Plugin name, publisher and dashboard product identity remain **Achuthan Jobs**. Public recruiting identity and emails use the configured employer name or current WordPress site title, so the same plugin can run at other employers without hard-coded company names.
- Existing role ID `bbs_recruiter`, job post type `bbs_job`, `bbs_` database tables, options, CSS classes, actions and legacy shortcode are **not renamed**: doing so would disconnect existing users, jobs and applications.
- New installations use the WordPress administrator email for recruitment notifications. **Existing email settings are never overwritten on upgrade.** Check the notification address in Achuthan Jobs → Settings.
- If your legal employer or data controller has a name different from Achuthan, update the job posting organisation and privacy notice to accurately describe it before recruiting.
- Historical copyright and prior GPL rights remain governed by LICENSE.md and are not silently changed by rebranding.

## Structured eligibility (v0.14.0)

In the WordPress **Jobs → Edit job** form, enter a numeric **Minimum relevant years of experience** such as `2`. The older free-text Experience field is informational and deliberately does **not** silently generate a screening rule. Under **Job application questions**, add a `Dropdown`, `Single choice`, `Multiple choice` or `Number` question; tick **Use as a qualifying requirement** and enter accepted answer(s) or numeric minimum. Save/update the job.

Applicants provide their relevant experience **separately for each role** when applying for several jobs. Qualification flags are calculated using only HR-configured rules and submitted structured answers, never applicant names, age, gender, résumé content, photos, or AI predictions. **Requirements not met** is a manual-review flag, not an automated decision, rejection, recommendation to exclude or hiring-stage change. Missing answers trigger **Needs HR review**. HR can read the explanation and manually re-evaluate when requirements change.

Existing applicants are **Not yet assessed** until an authorised HR member opens the application and re-evaluates it. Avoid job criteria unrelated to essential functions or disproportionately excluding protected groups. Check local employment and privacy law before use.

## GitHub auto-updates (v0.14.0)

Once this release is installed manually, future **published, tagged GitHub Releases** from `MohanaAchuthan30/achuthan-jobs` can be detected and installed by WordPress automatically. The GitHub Release must contain `achuthan-jobs.zip` and an asset digest; raw branch commits are not deployed. See [docs/GITHUB-AUTO-UPDATES.md](docs/GITHUB-AUTO-UPDATES.md). The updater verifies the ZIP checksum before installation. Enable or disable under Achuthan Jobs > Settings (administrators only).

## v0.16.0 — Messaging and screening

- Per job: edit **Candidate acknowledgement email** in the job editor (subject, text and safe placeholders). Multi-role applications get one message per newly accepted job application.
- Per site: set **Sender display name** and **From email address** under Achuthan Jobs → Settings. Use verified-domain SMTP (SPF/DKIM/DMARC); a mail transport accepting a message does not guarantee inbox delivery.
- HR can email an applicant and attach up to three validated PDF/DOCX/JPG/PNG files (3 MB each). Temporary copies are deleted after the send attempt. No malware scanning is provided.
- Eligibility question rules support option selections (any/all), numeric minimum/maximum, date ranges, and objective text phrases. Applications are **flagged for human review**, never automatically rejected. Filter Applications by eligibility outcome.
- A **persistent administrator update warning** appears when a verified newer GitHub Release is available. The standard WordPress update mechanism is used; it never forcibly installs unverified code.
- More details: [v0.16.0 upgrade and staging checks](docs/UPGRADE-TO-0.16.0.md).

## v0.17.0 — Native Elementor Job Listings widget

- Elementor **Free** detects **Achuthan Jobs — Job Listings** under Achuthan Jobs/General widget categories. No Elementor-specific code is needed to publish new vacancies.
- Four layouts: grid, list, compact, editorial. Column counts per device, amount, sort, department, job type, filters, headings, job metadata, Job IDs and optional excerpts.
- Native Elementor Style controls for backgrounds, hover state, accent and title colour, gap, padding and radius.
- Public AJAX refresh preserves widget-specific options. Saving a native widget suppresses the former automatic bottom-of-page list.
- Import the optional example template under `elementor/achuthan-careers-page.json`. See [setup and testing guide](docs/ELEMENTOR-NATIVE-WIDGET.md).
