# Achuthan Jobs v0.9 — Security and acceptance checklist

**Antivirus scanning is intentionally not required at the site owner's request.** Files are not certified free of malware. This is a conscious risk trade-off, not a security certification.

## Still enforced

- Private encrypted application/resume BLOBs using OpenSSL AES-256-GCM with site-derived key; tampering rejects decryption.
- Validates MIME, filename extension and basic file structure; PDFs must have PDF header and EOF and reject common active content indicators, images checked with getimagesize; max 3 MiB per file.
- No public URLs for applicant documents; downloads are admin-post POST actions, require HR capability, a candidate/file nonce, and attachment-only content response with `nosniff` and restrictive CSP.
- Bulk exports require HR capability, WordPress nonce and POST, plus 20-candidate / 80 MiB limit, sanitized ZIP member paths, and PHP ZipArchive.
- HTML output is escaped; WordPress prepare used for candidate lookups; input sanitization and permitted choices checked server-side.
- Per-job question validation on each selected job; duplicate job IDs in the same application are rejected; same-day duplicate candidate/job applications skipped.
- Honeypot, WordPress application nonce, expiry check and simple rate limiting. CDN/WAF remains necessary for serious bot traffic.
- Privacy policy requirement, retention task, audit records and selected CSV protections.

## Before installing on production

- [ ] Backup database, site, plugin files and WordPress auth salts; test restore.
- [ ] Use staging and a non-sensitive test account. Check role permissions so only trusted HR users can access candidate data.
- [ ] Verify HTTPS and no public WordPress uploads are created for resumes.
- [ ] Submit realistic fictional applications with custom questions for 1, 2 and 3 jobs. Test incomplete required answers, invalid file extensions, oversized files and missing consent.
- [ ] Confirm `post_max_size`, `upload_max_filesize`, PHP `max_file_uploads` and reverse proxy limits support the batch size; PHP ZipArchive is enabled.
- [ ] Verify applicant detail download and bulk ZIP when the candidate has no file questions, one file question, and multiple questions. Verify non-HR users are denied and CSRF tokens are checked.
- [ ] Verify rollback behaviour on forced database failures and check for partial/unlinked documents.
- [ ] Validate that editing, publishing, closing and reopening jobs updates `/careers/`, with Elementor caches enabled/disabled.
- [ ] Configure SMTP and check delivery, SPF, DKIM, DMARC and bounce handling.
- [ ] Configure backups, real cron retention, secure logging, WAF rate limiting and privacy requests.
- [ ] Run a professional security review of the public upload endpoint before real applicants submit CVs.

**Not a security guarantee:** A file may pass structural checks and still contain malicious payloads. Avoid distributing unscanned documents to unmanaged computers.

## v0.10.0 — Inline previews and application deletion

- [x] Two admin-only POST routes with HR capability and per-file nonce checks.
- [x] Validate decrypted PDF/image bytes and reject documents marked as rejected.
- [x] Inline delivery uses a sandboxed browser frame, private `no-store`, `nosniff`, explicit MIME types, CSP sandbox and no public attachment URL.
- [x] Preview requests are audit logged without applicant name/email.
- [x] Direct and bulk deletion remove only the selected application IDs and their encrypted screening-question attachments in a database transaction.
- [x] Destructive applicant-list controls use separate WordPress nonce forms and a confirmation prompt.
- [ ] Verify browser PDF viewer behaviour on actual admin sessions in Chrome, Firefox, Edge and Safari.
- [ ] Verify WordPress and Elementor staging upgrades, real database transactions and plugin interoperability.
- [ ] Security review with browser/hosting-specific headers and actual nonces.

**No antivirus scanner is required by this build, as requested.** Type validation, encryption and sandboxing do not eliminate malicious-file risk; candidate uploads remain untrusted.
