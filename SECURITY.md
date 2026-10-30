# Security policy — private project

**Do not open a public GitHub issue containing an exploit, applicant data,
credentials, database dumps, or resume files.** Report security problems
privately to the repository owner or the organization's authorized security
contact using a private channel.

## Threat model and controls

- The public application form is internet-accessible; apply rate limits and a
  WAF at the hosting edge. Use HTTPS end to end.
- Sensitive applicant documents are encrypted in the plugin storage and should
  only be available after authenticated capability, nonce, and request checks.
- Limit per-upload size to **3 MiB**, validate file signatures and reject files
  that fail validation. **No antivirus scanner is mandated by this version**:
  this creates residual malware risk, especially for PDFs and images.
- Do not expose private attachment records or authenticated preview/download
  endpoints to caches/CDNs.
- Use least-privilege recruiter/admin roles; remove unused accounts promptly.
- Set up reliable backups of both the database and the WordPress salts;
  losing/changing salts may make encrypted documents unreadable.
- Restrict who can download bulk applicant documents. Use retention/deletion
  policies and ensure backups are covered by the privacy policy.
- Keep WordPress, PHP, Elementor, theme and dependencies patched.
- Configure SMTP SPF/DKIM and verify candidate email actions in staging.

## Security release policy

No automatic deploys are configured. Human review, backups and staging
acceptance tests are required before any production update. GitHub Actions
only runs source checks and produces a downloadable, internal build artifact.

**This is not a penetration-test certification.** See docs/TESTING.md.
