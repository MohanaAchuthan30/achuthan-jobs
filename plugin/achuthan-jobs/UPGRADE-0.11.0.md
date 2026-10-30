# Achuthan Jobs v0.11.0 — Upgrade and verification

## What changed

1. **Job URLs:** `/careers/{job-slug}/` instead of `/careers/job/{job-slug}/`. Old job URLs redirect permanently to the new addresses after WordPress rewrite rules are refreshed.
2. **Multi-choice questions:** Real, theme-resistant checkboxes and radio buttons with semantic question groups; required checkbox questions are validated before submission and on the server.
3. **Careers listing:** By default, the installed WordPress page with slug `careers` is **rendered by Achuthan Jobs** using the site header and footer, instead of depending on static Elementor content. This means publishing/closing jobs updates `/careers/` on the next uncached load, and the JavaScript also fetches fresh listings. You can opt out in **Achuthan Jobs → Settings** to retain your own Elementor page and shortcode.

## Safe upgrade procedure

1. Take a full **database and file backup**, ideally on staging.
2. In **Plugins → Add New → Upload Plugin**, upload this ZIP and use **Replace current with uploaded** to update the existing `achuthan-jobs` plugin. Do not install as a second plugin.
3. Visit **Settings → Permalinks → Save Changes** once to refresh rewrite rules. This is necessary for new job URLs.
4. Verify a published job has URL `/careers/digital-marketing-executive/`; open its old `/careers/job/digital-marketing-executive/` link to check a 301 redirect.
5. Confirm there is a published WordPress **page** with slug `careers`. The default plugin setting takes over this page's content. If you need your previous Elementor layout, turn off **Automatically control /careers/** under the plugin settings and insert `[achuthan_jobs]` through Elementor.
6. Publish a dummy job using the Achuthan Jobs interface. Check that it appears on `/careers/` on a new private browser tab, and disappears when status changes to **Closed**. If not, purge any reverse proxy/CDN/Hostinger cache and check the public `admin-ajax.php?action=aj_live_jobs` response.
7. Create a required **Multiple choice** question and verify that each answer has a visible checkbox, several can be selected, and the form prevents submitting with none selected. Test **Single choice** radio options too.
8. Test an application with a safe, fictional PDF (under 3 MB) and confirm it appears in HR dashboard. Test the HR preview and email functions separately.

## Notes / limitations

- New job URL routing uses the registered `bbs_job` WordPress post type; this does **not** convert jobs into pages or portfolio posts.
- A WordPress page whose slug is `careers` must exist. WordPress caching/plugins or a CDN may still override cache-control headers; ensure `/careers/`, `/careers/*` and `admin-ajax.php` are uncached where appropriate.
- PHP syntax and local mocked assertions are **not equivalent** to testing your live Frisk/Elementor/hosting stack. This is a staging build.
- Existing applications, candidate data, custom questions and resumes are not deleted or migrated during this upgrade.
