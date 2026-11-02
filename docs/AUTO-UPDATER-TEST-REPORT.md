# v0.14.0 Auto-updater test report

- PHP lint and JS syntax: passed.
- Existing mock regression suite: 176 assertions passed.
- GitHub updater mock suite: 23 assertions passed, including checksum tamper failure and unrelated-plugin isolation.
- GitHub Actions files and ZIP directory layout: packaged and statically inspected.
- Live GitHub API authentication/availability, actual WordPress background updates, and live GitHub Actions publishing **not exercised** in this environment.
- Site-specific PHP permissions, WP-Cron, WP updates, Elementor interaction, release distribution, and installation rollback must be verified on staging.
- GitHub release digest validates downloaded bytes against a value controlled by GitHub; it is not a separate signing key.
