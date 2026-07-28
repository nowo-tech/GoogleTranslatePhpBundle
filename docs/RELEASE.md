# Release process

1. Update [CHANGELOG.md](CHANGELOG.md): move entries from `[Unreleased]` to a new `[X.Y.Z] - YYYY-MM-DD` section. (This project does not store version in `composer.json`; Packagist uses the git tag.)
2. Update [UPGRADING.md](UPGRADING.md) if the release has upgrade notes.
3. Run pre-release checks: `make release-check` (includes `check-no-cursor-coauthor`, cs-fix, cs-check, rector-dry, phpstan, test-coverage, and demo release-check).
4. Commit all changes, create an annotated tag (e.g. `v1.0.0`), and push branch and tag. The release workflow creates the GitHub Release from the changelog.
5. Confirm Packagist auto-update (or trigger manual sync).

Do **not** include Cursor `Co-authored-by` trailers in release commits (REQ-GIT-001). After the release commit (and before `git push`), re-run `make check-no-cursor-coauthor`. See [GITHUB_CI.md](GITHUB_CI.md).

## Example for v1.0.0

```bash
git add docs/CHANGELOG.md docs/UPGRADING.md docs/RELEASE.md docs/CONTRIBUTING.md
git status   # review
git commit -m "Release v1.0.0: initial public release"
git tag -a v1.0.0 -m "Release v1.0.0: initial public release"
git push origin main
git push origin v1.0.0
```
