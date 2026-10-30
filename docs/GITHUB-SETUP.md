# Private GitHub repository setup

The repo has **proprietary internal-use notices**, not a public/open-source
license. The source has a historical GPL license caveat (see `LICENSE.md`).

## Option A — terminal (recommended)

1. Sign in to GitHub and create a new repository named `achuthan-jobs`.
2. Set **Visibility: Private**. Do not add a template README, `.gitignore`,
   or license during creation (these are included here).
3. Download and **extract** the supplied GitHub repository ZIP. The ZIP is
   a source bundle, not a WordPress plugin ZIP.
4. Open a terminal in the extracted repository root (where `.gitignore` is).
5. Run:

```bash
git init
git branch -M main
git add .
git commit -m "Initial private Achuthan Jobs v0.11.0 source"
git remote add origin https://github.com/YOUR-ACCOUNT/achuthan-jobs.git
git push -u origin main
```

Replace `YOUR-ACCOUNT` with your GitHub username or organization. GitHub may
prompt you to sign in; use GitHub CLI or its credential manager. Don't embed
access tokens in the remote URL or commit them to this repository.

## Option B — GitHub CLI

Authenticate using `gh auth login`, then inside the extracted folder:

```bash
git init
git add .
git commit -m "Initial private Achuthan Jobs v0.11.0 source"
git branch -M main
gh repo create achuthan-jobs --private --source=. --remote=origin --push
```

The `--private` flag explicitly selects private repository visibility.

## Option C — GitHub website

Create **a new empty Private repository** and use **Add file → Upload files**
to upload the **extracted files**, not the GitHub ZIP itself. This is less
convenient for nested folder structures; the Git route is recommended.

## Settings to review immediately

- Verify the repository banner/settings say **Private**.
- Restrict collaborator access to authorized staff and approved contractors.
- Enable 2FA for members, branch protection and pull request review.
- Enable secret scanning/Dependabot if available for your plan/repository.
- Keep GitHub Actions permissions at **read-only** where practical.
- Do not enable GitHub Pages or publish source as a public release.
- Do not add a GitHub-generated public license (MIT, Apache, GPL template).

## Create a private plugin ZIP for WordPress

```bash
bash scripts/verify.sh
bash scripts/build-plugin.sh
```

Output: `dist/achuthan-jobs-v0.11.0-private.zip` (upload to staging WP).
The `.gitignore` excludes `dist/` so built ZIPs are not accidentally committed.

## How updates work

Edit code under `plugin/achuthan-jobs/`, raise version on actual releases,
commit changes, open a private pull request, review CI and test on WordPress
staging. The workflow **never auto-publishes to WordPress**.
