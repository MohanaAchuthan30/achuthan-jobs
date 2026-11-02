# Achuthan Jobs v0.16.0 — Upgrade and test guide

## What changed

1. On each job edit screen, **Candidate acknowledgement email for this job** lets HR set a plain-text subject and message. Available tokens: `{candidate_name}`, `{job_title}`, `{job_id}`, `{application_id}`, `{company_name}`. With multi-job submissions, each *newly created* application triggers its own job-specific acknowledgement; duplicates skipped by the existing workflow are not emailed again.
2. **Achuthan Jobs → Settings** now has an optional sender name and From address. An empty From address delegates to the site's SMTP setup. The HR notification destination is a separate field. Always use SMTP credentials and a verified sender-domain identity. Sending success does not prove receipt.
3. **Applications → View → Contact candidate** and the HR dashboard email composer support up to **3 files**, **3 MiB per file**. Accepted: PDF, DOCX, JPG and PNG. Files are inspected for extension/MIME/basic structure, sent through WordPress's mail system and deleted from temporary storage. No malware scan is performed. DOCX acceptance requires the PHP ZIP extension. Emails do not permanently save attachments to applicant records.
4. Each job-specific custom application question may be an HR screening rule: dropdown/radio accepted answers; multiple-choice *any* or *all*; number minimum and/or maximum; date earliest/latest; and text/textarea containing a specific, objective phrase. Unconfigured rules are skipped. Flags are for human review, **never automated rejection**. Eligibility filters are added to the HR Applications list.
5. **A persistent update notice** appears for admins with the `update_plugins` capability whenever the GitHub API reports a newer published, verified release. It links to the standard WordPress upgrader. Existing automatic update settings remain intact. GitHub **source pushes alone do not update sites**; a versioned Release with official `achuthan-jobs.zip` asset and digest is needed.

## Upgrade precautions

- Back up WordPress files and the database; preferably test on staging first.
- If installed plugins are on v0.15 or earlier, upload the ZIP using **Plugins → Add New → Upload Plugin → Replace existing**. The folder stays `achuthan-jobs`.
- Existing `bbs_job` post type, application tables, Job IDs and `/careers/{job-slug}/` routes are unchanged. Elementor retains control of the `/careers/` page: include `[achuthan_jobs_list]` where live jobs belong.
- Do not upload actual applicant resumes, credentials, SMTP secrets, database dumps or internal emails to public GitHub.
- For future updates, publish a Git tag matching `Version: 0.16.0` (e.g. `v0.16.0`). `.github/workflows/release.yml` produces `achuthan-jobs.zip` and the updater relies on the GitHub-hosted asset digest. Verify the release workflow completes and its ZIP directory starts with `achuthan-jobs/`.

## Staging acceptance checks — must be completed before live use

- Publish a test job, set a custom acknowledgement and apply using a dummy email. Verify subject, substitutions, sender and deliverability in Mailpit or a real test mailbox.
- Apply to two open jobs with distinct acknowledgement templates. Verify two candidate records and two different messages.
- Test the HR dashboard composer with 0, 1 and 3 valid attachments, invalid extensions, oversized file and DOCX. Verify recipient sees correct filenames and temporary files are deleted after sending.
- Test SMTP failure handling and a sender From address on the site's verified domain. Configure SPF/DKIM/DMARC. An inbox placement test is necessary.
- Create test questions with each rule type and submit passing, nonqualifying and missing answers. Check no application is rejected automatically, reasons display to HR, and filtering works.
- Simulate a newer versioned GitHub Release in staging: ensure the admin banner appears, the normal plugin update is available, checksum mismatch blocks installation, and no other plugins are affected.
- Test existing applicant previews/downloads, 3 MB application file limits, HR permissions, recruitment data deletion, and Elementor frontend on mobile.
- Ensure WordPress backups, HTTPS, WAF/rate limiting and retention/privacy notices are in place. The applicant files and email attachments are **not antivirus-scanned**; treat untrusted documents as a risk.

## Compatibility and limitations

Targets WordPress 6.2+ / PHP 8.0+, not every historical or future WordPress/Elementor version. This release was linted and tested with a mocked WordPress API only; **no real WordPress instance or mail transport was run** in this environment. Run staging tests before production.
