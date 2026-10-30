# Testing and release checklist

The current package is v0.12.0 **staging**. The embedded mock smoke test is
useful but does not prove server security or production correctness.

## Automated checks

```bash
bash scripts/verify.sh
bash scripts/build-plugin.sh
```

- Lint all plugin PHP and run Node syntax checks for JavaScript assets.
- Run existing mocked WordPress/security smoke test.
- Validate build ZIP integrity and expected plugin folder structure.

## WordPress end-to-end acceptance testing (required)

- Job creation, edit, publish, close, reopen, bulk import and bulk deletion.
- Auto-generated and custom Job IDs, canonical permalink and old redirect.
- Listing visibility on fresh load, after publish/close and with caching.
- Both plugin-controlled `/careers/` and Elementor shortcode modes.
- Single-choice radios and multi-choice checkboxes (multiple independent
  selections), required input, spoofed option rejection.
- One candidate applying to up to three different roles; duplicate handling.
- PDF/JPG/PNG file question uploads: 3 MiB caps, MIME/signature checks,
  invalid/oversize files, unsupported files, size of full POST body.
- Candidate view and in-dashboard secure preview of resume/attachments.
- Individual and bulk (ZIP) file downloads; confirm permission boundaries.
- Deleting one application leaves same person's other applications intact.
- Verify HR email sending and failure messages (SMTP on staging).
- Test capability & nonce checks and rate limiting in a genuine WordPress
  instance; test logged-out and lower-privilege user paths.
- Verify retention, privacy notices, accessibility, mobile behavior.
- Restore a backup and verify encrypted files can still be decrypted when
  the WP salts are preserved.
- Test with installed versions of Frisk/Elementor/WordPress/PHP, not a claim
  of compatibility with every version.

## Known risks

- No real WordPress integration/penetration test was performed when creating
  the earlier plugin package; mocks cannot replace these tests.
- No antivirus is required; documents can contain malicious content even if
  type and structure validation passes.
- GitHub Actions build artifacts are not deployed automatically.
