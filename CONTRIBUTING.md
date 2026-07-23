# Contributing

Thank you for considering a contribution.

## The one rule that is not negotiable

**No change may make an invalid identification number pass.**

The format test, the leading-digit test and the checksum together define what a
valid number *is*. They are not features to be toggled. A patch that adds a
setting, a flag, an environment check or a "legacy mode" that lets a failing
number through will not be accepted, however it is framed.

If you have real data that fails the checksum, the answer is a migration that
records and reports it, or a fixture generator that produces correct numbers —
not a weaker validator.

## Getting set up

The module needs no build step. Symlink or copy it into a Drupal site:

```bash
drush en saudi_id_validator
```

## Running the tests

```bash
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/contrib/saudi_id_validator/tests
```

Everything must pass before a patch is considered. There are unit tests (no
container), kernel tests (container and entity constraints), and a test that
guards the module's independence.

## Coding standards

```bash
vendor/bin/phpcs --standard=Drupal,DrupalPractice \
  --extensions=php,module,inc,install,yml \
  web/modules/contrib/saudi_id_validator
```

This must report zero errors and zero warnings. `phpcbf` fixes most of what it
finds; read what it changed rather than trusting it blindly.

## Never hardcode an identification number

Not in a test, not in a fixture, not in documentation. Use the generator:

```php
use Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator;

$ids = new SaudiIdGenerator();
$ids->nationalId();       // valid, starts with 1
$ids->iqama();            // valid, starts with 2
$ids->wrongChecksum();    // right shape, wrong check digit
```

A literal ten-digit number cannot be checked by eye against the check digit. One
that silently fails it makes a test pass for the wrong reason — which is exactly
the bug this module exists to prevent.

## The module must stay standalone

`saudi_id_validator` may depend on Drupal core and nothing else. It is meant to
drop into a customer portal, a CRM, an HR system or a commerce site without being
edited, and that only stays true while it knows about no other module.

`ModuleIsStandaloneTest` enforces this: it scans every file for a `use` outside
core's namespaces and fails on the first one. If your patch needs another
module, it belongs in a different module that consumes this one.

## Public API and versioning

The module follows semantic versioning. The public API is everything marked
`@api`, plus:

- the service names `saudi_id_validator.validator` and
  `saudi_id_validator.settings`, and the interface as a service id;
- the constraint plugin id `SaudiId` and its `requireType` option;
- the configuration object `saudi_id_validator.settings`;
- the number a `SaudiIdGenerator` sequence produces.

Anything marked `@internal` may change in any release. If your patch changes
something `@api`, say so — it decides whether the next release is a major one.

## Adding a rule or a scheme

`LuhnChecksum` is a separate class so a scheme with a different check digit can
be added *beside* it, not edited into it. Consumers inject
`SaudiIdValidatorInterface`, so an alternative implementation can be bound in
its place without any consumer changing.

If you are adding support for another country's identification numbers, that is
a new class and probably a new module — not a branch inside this validator.

## Submitting

1. One logical change per patch.
2. Tests that fail before your change and pass after it.
3. A CHANGELOG entry under `## [Unreleased]`.
4. No unrelated reformatting — a reviewable diff is a kindness.

## Reporting a security issue

Do not open a public issue. See [SECURITY.md](SECURITY.md).
