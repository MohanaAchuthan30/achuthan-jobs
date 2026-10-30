# Upgrade the private Achuthan Jobs repository to v0.12.0

The repository remains PRIVATE. This version **rebrands displayed text** without changing WordPress job URLs or database identifiers.

## WordPress upgrade

1. Make a full database and plugin backup. Use a staging copy first.
2. Upload the separate `achuthan-jobs-v0.12.0-wordpress-plugin.zip` via **Plugins → Add Plugin → Upload Plugin**.
3. If prompted, select **Replace current with uploaded**. Do not install under a different plugin folder.
4. Verify the plugin name **Achuthan Jobs**, author **Achuthan**, version **0.12.0**.
5. Open **Achuthan Jobs → HR Dashboard** and test the Careers page, job detail page, email composer and an application using dummy details.
6. Check **Achuthan Jobs → Settings** for the saved notification email; upgrading does not change an existing recipient.
7. Make sure privacy notices identify the actual legal data controller and that JobPosting structured data names the real employer.

## Update the GitHub repository with the new source

You can use GitHub Desktop, or upload the changed files through GitHub's website. The repository ZIP contains extracted sources (`plugin/`, `docs/`, `scripts/`, `.github/`) and should **not** be committed as a binary ZIP. Preserve the folder structure. The `.git` folder is intentionally not included. Keep the repository private.

**Do not upload** candidate data, resumes, CSV exports, database backups, `.env`, WordPress passwords or salts.

If GitHub already has the v0.11.0 files, replace changed files rather than uploading duplicate nested folders. Alternatively clone the private repository with GitHub Desktop, copy the contents of this source package over the clone, inspect the changes, and commit/push.
