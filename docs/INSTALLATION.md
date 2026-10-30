# WordPress staging install and upgrade

**Pre-flight:** full database/file backup; restrict access to staging;
WordPress 6.2+, PHP 8.0+; OpenSSL and fileinfo extensions, plus PHP `zip`
for bulk ZIP exports; SMTP configured; hosting upload/post limits checked.

1. Build the plugin ZIP with `bash scripts/build-plugin.sh`.
2. WordPress → Plugins → Add New → Upload Plugin. Choose ZIP from `dist/`.
3. When replacing a prior Achuthan Jobs version, use **Replace current**.
   Keep the existing plugin slug `achuthan-jobs`. Do not install duplicate
   recruitment plugins that share the same custom job post type/data tables.
4. WordPress → Settings → Permalinks → Save Changes once.
5. Ensure a published WordPress page exists with slug `careers`.
6. Achuthan Jobs Settings: enable automatic control of `/careers/` to use the
   plugin-owned listing. If you want Elementor layout instead, disable the
   override and use the `[achuthan_jobs]` Shortcode widget.
7. Verify a new published job appears on `/careers/` and at
   `/careers/{job-slug}/`; confirm older `/careers/job/{job-slug}/` links
   redirect properly. Clear Hostinger/CDN/WordPress cache if stale.
8. Test multi-select custom questions and a fictional application with
   a *safe* 3 MiB-or-smaller PDF and small image attachment.
9. Check applicant records, preview, individual & bulk download, deletion,
   bulk stage changes, HR email sending and role access controls.
10. Confirm privacy notice/retention and audit log behavior before launch.

## Rollback

Keep a database backup and the previously deployed plugin ZIP; roll back in
staging first, then in production only with a tested procedure. Database
schema changes may not automatically reverse when older plugin files are
restored. Avoid using real applicant data until acceptance tests pass.
