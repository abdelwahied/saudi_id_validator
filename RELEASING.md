# Releasing

A step-by-step checklist for cutting a release of the Saudi ID Validator module.
It assumes no prior knowledge of the project: follow it top to bottom.

Throughout, `X.Y.Z` is the version being released (for example `1.0.0`).

## 1. Prepare

- [ ] Work from a clean checkout of the branch you are releasing from
      (`git status` shows nothing uncommitted).
- [ ] Confirm you are on the intended branch (`main` for the current major).

## 2. Run the test suite

The module has no Drupal install of its own; tests run inside a Drupal site with
the module placed in `web/modules/custom/`. From that site's root:

```bash
SIMPLETEST_DB="sqlite://localhost/db.sqlite" \
  ./vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/saudi_id_validator/tests
```

- [ ] Every test passes.

CI runs the same suite across PHP 8.3/8.5 and Drupal 10.3/11 on every push; a
green run on the release commit is the authoritative check.

## 3. Run the coding-standards check

```bash
./vendor/bin/phpcs --standard=Drupal,DrupalPractice \
  --extensions=php,module,inc,install,yml,js \
  web/modules/custom/saudi_id_validator
```

- [ ] Zero errors and zero warnings.

## 4. Review the changelog

- [ ] [CHANGELOG.md](CHANGELOG.md) has an entry for `X.Y.Z` describing every
      notable change since the last release.
- [ ] The entry is dated and the "Unreleased" heading (if any) is moved down.

## 5. Update documentation

- [ ] [README.md](README.md) examples still match the code.
- [ ] [API.md](API.md) lists the current public surface.
- [ ] [UPGRADING.md](UPGRADING.md) has a section for `X.Y.Z` if any manual step
      is needed (none for a patch or minor).
- [ ] Version references in prose and badges are correct.

## 6. Verify composer metadata

```bash
composer validate --strict --no-check-all
```

- [ ] Passes.
- [ ] `name`, `description`, `license`, `keywords`, `require` and `authors` are
      accurate.
- [ ] No `repositories`, path repositories or VCS repositories are present.

## 7. Verify the README examples

- [ ] Every service ID, route name and configuration key named in the README
      exists in the code (`grep` the module, or enable it on a scratch site and
      check with `drush`).

## 8. Tag the release

Drupal.org and Composer both read the tag as the version, so it must match
`X.Y.Z` with no `v` prefix for Drupal.org contrib.

```bash
git tag -a X.Y.Z -m "Saudi ID Validator X.Y.Z"
```

- [ ] Tag created on the reviewed commit.

## 9. Push the tag

```bash
git push origin X.Y.Z
```

- [ ] Tag pushed. CI runs against the tag.

## 10. Publish release notes

- [ ] Create the release on the hosting platform (GitHub release or Drupal.org
      release node) using the `X.Y.Z` CHANGELOG entry as the notes.
- [ ] Confirm the packaged archive installs cleanly on a fresh site.

## Notes for a repository split

This module currently lives alongside others in one repository. When it is moved
to its own repository, copy `.github/workflows/ci.yml` and
`.github/workflows/reusable-drupal-module.yml` into it and change the module
path in the reusable workflow from `modules/custom/saudi_id_validator` to the
repository root.
