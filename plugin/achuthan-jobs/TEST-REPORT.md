# Achuthan Jobs 0.12.0 — Local test report

## Checks performed

- PHP syntax validation: main plugin, question handler, native Careers template, native single-job template, and smoke-test harness passed.
- Node JavaScript syntax validation: frontend, question builder, and private preview scripts passed.
- 156 in-memory WordPress smoke-test assertions passed, including new URL configuration, legacy route registration, dynamic Careers template selection, rendered checkbox/radio inputs, per-job array field names, required-answer rejection and option spoofing rejection.
- Headless Chromium with an artificial Frisk-like stylesheet that hides checkboxes confirmed the plugin rules restore visible 20×20 controls and allow independent multiple selections.
- ZIP integrity and plugin folder layout validated.

## Not verified

- This environment has no running WordPress site, MariaDB or actual Elementor/Frisk instance. We could not test the live `/careers/` URL, actual rewrite resolution on bigboxstudio.in, fresh published jobs there, a real applicant submission or host caching behaviour.
- No security certification/penetration test was performed. Prior security controls and file restrictions are retained, not newly certified.

**Release is staging-only until the site's acceptance tests pass.**
