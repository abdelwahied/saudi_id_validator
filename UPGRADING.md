# Upgrading

## Introduction

This document explains how to move between versions of the Saudi ID Validator
module and what compatibility you can rely on. It complements
[CHANGELOG.md](CHANGELOG.md), which records what changed in each release;
this file records what you have to *do* about it.

## Version policy

The module follows [Semantic Versioning](https://semver.org/):

- **Patch** releases (`1.0.x`) fix bugs and never change behavior you could
  depend on.
- **Minor** releases (`1.x.0`) add functionality in a backward-compatible way.
  Existing code, configuration and the public API keep working.
- **Major** releases (`2.0.0`, …) may change or remove public API. Every
  breaking change is documented in this file, with a migration path.

The public API surface that this policy protects is the one marked `@api` and
described in [API.md](API.md): the `saudi_id_validator.validator` service and
its `SaudiIdValidatorInterface`, the `SaudiId` constraint, the form-element
validator, and the `saudi_id_validator.settings` configuration object.

## Upgrade process

For a patch or minor release within the same major version:

1. Update the code (`composer update abdelwahied/saudi_id_validator`, or replace
   the module directory).
2. Run database updates: `drush updatedb`.
3. Rebuild caches: `drush cache:rebuild`.

No manual steps are ever required for a patch or minor release.

## Version 1.0.0

This is the first stable release. **No upgrade steps are required** — there is
no earlier version to come from.

## Compatibility policy

- **Drupal**: `^10.3 || ^11`. A minor release will not raise the minimum below
  what a supported Drupal core still receives security coverage for.
- **PHP**: `>= 8.3`.
- Dropping support for a Drupal or PHP version is a breaking change and will
  only happen in a major release, announced here.

Future major versions will document their breaking changes and migration steps
in this file.
