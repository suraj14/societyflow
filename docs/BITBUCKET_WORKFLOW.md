# Bitbucket repositories and branch workflow

| Repository | Content | Branches (as of 2026-10-24) |
|---|---|---|
| https://bitbucket.org/suraj/societyflow-web | Laravel web + API (this folder) | `production-readiness` (the application), `integration/main-merge` (production-readiness merged into main, ready for a pull request), `main` (**only** an initial `.gitignore`, commit 5f89509) |
| https://bitbucket.org/suraj/societyflow-mobile | Flutter app (`G:\xampp\htdocs\societyflowmob`, **not** inside this folder any more) | `main` (first commit ec40d83) |

The old GitHub remote `origin` (suraj14/societyflow) is still configured in this clone; its `main` has the first commits only.
The Bitbucket remote in this clone is named `bitbucket`.

## Why `main` is not simply updated

`main` and `production-readiness` have **no common ancestor**. `integration/main-merge` is a merge commit with both as parents whose tree is exactly
`production-readiness` (the only overlapping file, `.gitignore`, resolves in favour of the application's own). Because `bitbucket/main` is an ancestor of it,
moving `main` there is a plain fast-forward - no force push, no history lost. Do it through a pull request
(`https://bitbucket.org/suraj/societyflow-web/pull-requests/new?source=integration/main-merge&t=1`, destination `main`) or, with approval, `git push bitbucket integration/main-merge:main`.

## Day to day

* Work on a feature branch from `production-readiness`; merge back through a pull request after `php vendor/phpunit/phpunit/phpunit` (SQLite) and, before a release, the MariaDB run (docs/TESTING.md).
* Never `git push --force`, `git reset --hard` or `git clean` on shared branches. Never commit `.env*` (only `.env.example`), databases, `storage/`, keystores or backups.
* Deploy only from a tagged commit: `git tag -a rc-YYYY-MM-DD -m "release candidate"` then build the package (docs/DEPLOYMENT_CLOUDPANEL.md). No release tag exists yet.
* The mobile repository is separate; its API contract is the JSON the Laravel API returns (see docs/API_COMPATIBILITY.md).

## Known repository hygiene items

* `.env.mysql.template` carried a real `APP_KEY` from the first commit until c939ffa: treat that key as exposed (TODO P0-20). History was not rewritten.
* A nested clone `societyflow-web/` and a stray text file `SocietyFlow` sit untracked in the project root; they are excluded locally (`.git/info/exclude`) and must not be committed. The mobile folder has two stray files (`h -u origin main`, `societyflowmob`) from mistyped commands; they are untracked and safe to delete.

## Pipelines (added 2026-10-24)

`bitbucket-pipelines.yml` (web) and the mobile repository's own `bitbucket-pipelines.yml` run the tests on every push and pull request. Only the web branch
`production-readiness` carries a production deploy step, and it is a **manual** button until the checklist in [BITBUCKET_PIPELINES_SETUP.md](BITBUCKET_PIPELINES_SETUP.md)
section 7 is signed off. The mobile pipeline never deploys the backend and never publishes the app. Your everyday Git commands are in section 9 of that guide and in the mobile `README.md`.
