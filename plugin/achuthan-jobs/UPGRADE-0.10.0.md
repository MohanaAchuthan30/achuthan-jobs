# Achuthan Jobs v0.10.0 — Preview & Application Deletion

**Release status:** Staging/testing build, not certified for production deployment.

## What's new

- HR can preview a candidate's **PDF resume** directly in **Achuthan Jobs → Applications → View / preview** without using the Download button.
- HR can preview **PDF, JPG, JPEG and PNG** files attached to job-specific questions, in the same viewer.
- The viewer uses an isolated iframe, authenticated POST requests, record-bound WordPress nonces, HR capability checks, encryption/decryption within PHP, content validation and `no-store` / `nosniff` / sandbox response headers. Files are not placed in WordPress Media Library or exposed via public URLs.
- Applicant list has an individual **Delete** button with a confirmation dialog. An individual application profile retains **Permanently delete application** and the list retains **bulk delete**.
- Deleting an application now runs in a database transaction, removing its private attachment records and application row together, while preserving any separate applications from the same email address. The deletion audit event is retained with a numeric record ID (no candidate name/email).
- The existing Careers shortcode, 3 MB/file limit, multi-job application support, job IDs, email capability, candidate stages, and individual/bulk downloads are unchanged.
- Poppins styling is retained.

## Installing as an upgrade

1. Back up the WordPress files and database, including existing applicant files.
2. Install this ZIP on a **staging clone** of bigboxstudio.in. In WordPress choose **Plugins → Add Plugin → Upload Plugin**, select the ZIP and choose **Replace current with uploaded** if prompted.
3. Visit **Achuthan Jobs → Applications**.
4. Open a test application and click **Preview file** beside its resume; repeat with a PDF/image custom-question attachment. Test desktop and mobile browsers; if a browser doesn't support inline PDF preview, the existing **Download PDF** action remains available.
5. Test removing an application: click **Delete** in the applicant list, cancel once, then confirm on a dummy record. Confirm that the row and attached question files disappear while another job application by the same candidate remains.
6. Test bulk delete using dummy applicants. Check the HR permissions and admin session. Verify new jobs still appear automatically on `/careers/` via `[achuthan_jobs]`.
7. Verify SMTP delivery and backup/restoration before promoting to production.

## Security and privacy notes

- Only logged-in users with `manage_bbs_applicants` can request file previews. Anonymous access is not registered.
- Preview uses authenticated POST with a per-file, session-bound WordPress nonce. Uploaded files remain in encrypted private database records.
- **No malware scanner is required**, per site owner's request. PDF/image validation is not equivalent to antivirus scanning. HR should only preview unknown documents on patched browsers and managed workstations.
- The browser does receive the document bytes to display a preview; `no-store` headers discourage caching but cannot prevent screenshots or all endpoint/browser-level retention.
- Permanent deletion removes application/file rows from active database, **not** historical backups, database transaction logs or documents previously downloaded by HR.
- All changes should be tested on a real WordPress/Elementor staging instance. The smoke-test harness simulates WordPress and does not prove production compatibility.
- Avoid uploading real candidate data during staging testing; use dummy documents and fictional email addresses.
