# Changelog

All notable changes to this module are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the module follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

What "breaking" means here is defined by the public API: anything marked `@api`
in its docblock, the `SaudiId` constraint plugin id and its options, the service
names, the configuration object, and the values a generated test number takes
for a given sequence. Classes marked `@internal` may change in any release.

## [Unreleased]

Nothing yet.

## [1.0.1] — 2026-07-23

### Fixed

- Documented the element validator's by-reference `$element` parameter as the
  generic array the Form API provides, clearing a PHPStan level 4 by-reference
  type warning.

No functional or public API changes.

## [1.0.0] — 2026-07-22

First release.

### Added

- `SaudiIdValidatorInterface` and its implementation: `isValid()`,
  `detectType()`, `isSaudiCitizen()`, `isResident()` and `getMetadata()`.
- Offline validation of the three rules — ten ASCII digits, a leading digit of
  1 (National ID) or 2 (Iqama), and the official Luhn check digit. No registry
  is contacted and no HTTP request is made.
- `LuhnChecksum`, the check-digit arithmetic as a standalone, container-free
  class, including `checkDigit()` for generating a completing digit.
- `IdType` and `FailureReason` enums, and the immutable `IdMetadata` verdict.
- `SaudiIdElementValidator` for Form API `#element_validate`.
- The `SaudiId` validation constraint, which covers entity forms, JSON:API,
  REST, migrations and programmatic saves at once, with an optional
  `requireType` to restrict a field to one type of number.
- Automatic validation of fields by machine name, configurable at
  Administration → Configuration → System → Saudi ID Validator. Ships watching
  `national_id`, `saudi_id`, `identity_number`, `iqama` and `id_number`.
- `SaudiIdValidatedEvent` and `SaudiIdValidationFailedEvent`, carrying the value
  as submitted alongside the verdict.
- `SaudiIdGenerator`, a test-data generator producing valid numbers, wrong
  checksums, unusable leading digits, wrong lengths and malformed input. Numbers
  are derived from a sequence, so a failing test reproduces exactly.
- Arabic translation of every user-facing string.

### Security

- Only ASCII digits are accepted. Arabic-Indic digits, zero-width characters and
  non-breaking spaces are rejected rather than folded into ASCII, so a value that
  looks correct but carries invisible characters cannot reach storage.
- Surrounding whitespace is trimmed; whitespace inside a number is not.

### Notes

- There is deliberately no setting that disables the checksum. A failing check
  digit means the number is not a real one, which is not a site preference. A
  site importing legacy data that was never checksummed should handle that in
  the migration, not by weakening validation for everybody.

[Unreleased]: https://github.com/abdelwahied/saudi_id_validator/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/abdelwahied/saudi_id_validator/releases/tag/v1.0.0
