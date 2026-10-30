# Achuthan Jobs v0.12.0 — local rebrand checks

- PHP lint: all plugin PHP files passed.
- JavaScript syntax check: all plugin JavaScript files passed.
- Mock WordPress security and workflow harness: 156 assertions passed (inherited mocked checks).
- Additional static branding and backward-compatibility assertions: passed.
- Package ZIP verified for corruption and expected plugin folder layout.

These are **static and mocked checks**, not a real WordPress / Elementor / Hostinger test. Full application submission, mail delivery, job listing refresh, privacy-policy wording, recruiter role upgrades and existing candidate records require staging verification.

The code retains legacy internal keys such as `bbs_job`, `bbs_recruiter`, `bbs_` SQL tables and shortcodes to preserve prior records. These are not public-facing company labels.
