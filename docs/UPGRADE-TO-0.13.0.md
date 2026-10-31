# Achuthan Jobs v0.13.0 — Staging upgrade checklist

This version upgrades the existing `achuthan-jobs` plugin folder in place. It does **not** create a second plugin or rename legacy `bbs_` data keys.

1. Back up the WordPress database and plugin files. **Preserve WordPress salts**: encrypted applicant files use site secrets.
2. Install this ZIP on a staging clone. On WordPress admin load, a site administrator runs the plugin database migration automatically. Confirm `achuthan_jobs_schema_version` is `0.13.0` and that the `wp_bbs_applications` table (your actual table prefix may differ) has the `candidate_experience`, `eligibility_status`, and `eligibility_detail` columns.
3. Open **Achuthan Jobs → Settings**. Set the **Employer name** to the company operating this website. Optional country uses a 2-letter ISO code; do not leave a previous employer name on a new site.
4. Edit a test Job; set **Minimum relevant years** to `2`. Add a custom single-choice question such as `Do you hold the required certification?` with options `Yes` and `No`. Enable **Use as a qualifying requirement** and set `Yes` as the accepted answer. Publish.
5. Submit fictional test applications: `1 year + No` → **Requirements not met**, `3 years + Yes` → **Meets stated requirements**, skipped years/answers → **Needs HR review**. Check reasons inside the HR dashboard. Ensure **stage** remains New for all.
6. Submit a candidate to 2 different jobs in one form and verify each application has a separate role-specific eligibility result.
7. Click **Re-evaluate against current job requirements** on a test candidate. Verify only authorised HR can act and that it updates result, not stage.
8. Verify `/careers/` updates, `<site>/careers/<job>/` URLs, emails, custom questions, 3 MB uploads, previews, bulk downloads, and retention. Test with Elementor both via the shortcode and plugin-owned Careers page.
9. On sites without a `careers` page, create a WordPress page or place `[achuthan_jobs]` on another page. The plugin does not create your public page automatically.
10. Test and approve the staging version before installing it on your live site.

**Caution:** Qualification flags are informational, based on self-reported structured answers. They are not proof of eligibility and never auto-reject. Historical applications need manual re-evaluation. No malware scanning is enforced by default; uploaded files remain a security risk.

**Compatibility:** WordPress 6.2+ / PHP 8.0+ are the claimed minimums, not a guarantee for all future or historical versions. Version compatibility and security still require live staging testing.
