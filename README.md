# Saudi ID Validator

[![CI](https://github.com/abdelwahied/saudi_id_validator/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/abdelwahied/saudi_id_validator/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/abdelwahied/saudi_id_validator?sort=semver)](https://github.com/abdelwahied/saudi_id_validator/releases/latest)
[![License](https://img.shields.io/github/license/abdelwahied/saudi_id_validator)](LICENSE.txt)
[![Drupal](https://img.shields.io/badge/Drupal-%5E10.3%20%7C%7C%20%5E11-blue.svg)](https://www.drupal.org)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.3-blue.svg)](https://www.php.net)

> **Compatibility:** Drupal `^10.3 || ^11`, PHP `>= 8.3`. **Version:** 1.0.0.

Validates Saudi identification numbers — National IDs and Iqama (resident)
numbers — entirely offline. No registry is contacted, no HTTP request is made,
and no database is read. A number that passes is well formed; it is not a claim
that the person exists.

## What it checks

Three rules, applied in this order so the message names the actual mistake:

1. **Format** — exactly ten ASCII digits.
2. **Type** — the leading digit: `1` is a National ID, `2` is an Iqama.
   Anything else is not an identification number.
3. **Checksum** — the official Luhn check digit.

The third rule is the one that matters most and the one a regular expression
cannot express. `1234567890` looks perfectly well formed and is not a real
number: its weighted total is 43, not a multiple of ten.

There is no setting that turns any of these off.

## Documentation

| | |
| --- | --- |
| [API.md](API.md) | Full public API reference, with the stability contract |
| [CHANGELOG.md](CHANGELOG.md) | What changed, and when |
| [UPGRADING.md](UPGRADING.md) | Version and compatibility policy, and upgrade steps |
| [CONTRIBUTING.md](CONTRIBUTING.md) | How to work on it |
| [RELEASING.md](RELEASING.md) | The release checklist for maintainers |
| [SECURITY.md](SECURITY.md) | Reporting a vulnerability, and what the module does and does not guarantee |

## Installation

```bash
drush en saudi_id_validator
```

## Usage

### The service

Inject it. Never call it statically.

```php
use Drupal\saudi_id_validator\SaudiIdValidatorInterface;

final class MyService {

  public function __construct(
    private readonly SaudiIdValidatorInterface $saudiIds,
  ) {}

  public function check(string $id): void {
    $this->saudiIds->isValid($id);          // bool
    $this->saudiIds->isSaudiCitizen($id);   // bool — a National ID
    $this->saudiIds->isResident($id);       // bool — an Iqama
    $this->saudiIds->detectType($id);       // IdType|null
    $this->saudiIds->getMetadata($id);      // IdMetadata
  }

}
```

In `mymodule.services.yml`:

```yaml
services:
  Drupal\mymodule\MyService:
    arguments:
      - '@Drupal\saudi_id_validator\SaudiIdValidatorInterface'
```

The alias `@saudi_id_validator.validator` resolves to the same instance.

### The metadata

`getMetadata()` returns an immutable value describing the verdict, including why
an invalid number failed:

```php
$verdict = $this->saudiIds->getMetadata($id);

$verdict->valid;      // bool
$verdict->type;       // IdType|null
$verdict->reason;     // FailureReason|null
$verdict->toArray();  // ['valid' => TRUE, 'type' => 'IQAMA', 'label' => …]
```

Each `FailureReason` carries its own translated message, so a form can say what
went wrong without inventing wording:

```php
if (!$verdict->valid) {
  $form_state->setErrorByName('national_id', $verdict->reason->message());
}
```

### Form API

```php
use Drupal\saudi_id_validator\Validator\SaudiIdElementValidator;

$form['national_id'] = [
  '#type' => 'textfield',
  '#title' => $this->t('National ID'),
  '#element_validate' => [
    [SaudiIdElementValidator::class, 'validate'],
  ],
];
```

An empty value is left alone — whether the field is mandatory is `#required`'s
business, not the validator's.

### Entity fields

Attaching the constraint covers **every** way into the entity at once — entity
forms, JSON:API, REST, migrations, and code that saves programmatically — since
they all run the entity's own constraints:

```php
$fields['national_id'] = BaseFieldDefinition::create('string')
  ->setLabel(t('National ID'))
  ->addConstraint('SaudiId');
```

To accept only one type:

```php
use Drupal\saudi_id_validator\IdType;

$fields['national_id']->addConstraint('SaudiId', [
  'requireType' => IdType::SaudiNationalId->value,
]);
```

An invalid value submitted through JSON:API returns a standard `422` with the
violation message. No custom endpoint is involved.

### Automatic validation

Fields are validated by machine name without any code, for these names by
default:

`national_id`, `saudi_id`, `identity_number`, `iqama`, `id_number`

The list and the on/off switch live at **Administration → Configuration →
System → Saudi ID Validator**. This setting controls *where validation is
added*, never where it is skipped: a field that attaches the validator or the
constraint itself is always validated.

### Events

```php
use Drupal\saudi_id_validator\Event\SaudiIdValidatedEvent;
use Drupal\saudi_id_validator\Event\SaudiIdValidationFailedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MySubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      SaudiIdValidatedEvent::class => 'onValidated',
      SaudiIdValidationFailedEvent::class => 'onFailed',
    ];
  }

  public function onValidated(SaudiIdValidatedEvent $event): void {
    $event->originalValue;   // as submitted
    $event->metadata->type;  // IdType
  }

  public function onFailed(SaudiIdValidationFailedEvent $event): void {
    $event->metadata->reason;  // FailureReason
  }

}
```

Subscribers observe; they cannot change a verdict that is already decided.

## Architecture

| Class | Responsibility | |
| --- | --- | --- |
| `SaudiIdValidatorInterface` | The contract consumers inject | public |
| `Checksum\LuhnChecksum` | The arithmetic alone — testable without a container | public |
| `IdType` | The two kinds, their leading digits and labels | public |
| `FailureReason` | Why a number was rejected, and how to say so | public |
| `IdMetadata` | The immutable verdict | public |
| `Validator\SaudiIdElementValidator` | Form API adapter | public |
| `Plugin\…\SaudiIdConstraint` | The `SaudiId` constraint | public |
| `Event\SaudiIdValidated*Event` | What subscribers receive | public |
| `ValidatorSettings` | Typed read access to configuration | public |
| `SaudiIdValidator` | The three rules, in order | internal |
| `Plugin\…\SaudiIdConstraintValidator` | Typed-data adapter | internal |
| `Form\SettingsForm` | The settings screen | internal |

The adapters hold no rules. They translate between their world and the
validator, so a form field and an API write can never disagree about what a
valid number is.

Internal classes are implementation details: inject
`SaudiIdValidatorInterface` rather than the implementation, and attach the
`SaudiId` constraint rather than naming its validator. See
[API.md](API.md#what-is-internal).

## Testing

```bash
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/saudi_id_validator/tests
```

### Never hardcode an identification number in a test

Use the generator:

```php
use Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator;

$ids = new SaudiIdGenerator();

$ids->nationalId();            // valid, starts with 1
$ids->iqama();                 // valid, starts with 2
$ids->nationalId(7);           // a different one; same argument, same number
$ids->many(IdType::Iqama, 50); // fifty distinct valid numbers

$ids->wrongChecksum();         // right shape, wrong check digit
$ids->unknownLeadingDigit();   // right checksum, unusable leading digit
$ids->wrongLength(9);          // nine digits
$ids->malformedSamples();      // letters, symbols, scripts, Arabic-Indic
                               // digits, zero-width and non-breaking spaces
```

A literal ten-digit number in a test is a small landmine: nobody can tell by eye
whether it satisfies the check digit, and one that silently fails it makes the
test pass for the wrong reason. Generated numbers are correct by construction
and stay correct if the rules change.

Numbers are derived from a sequence, not drawn at random, so a failing test
reproduces exactly. `randomValid()` exists for fuzzing, where breadth matters
more than repeatability.

The generator lives under `tests/`, so it ships with the module but never loads
in production. Any module's tests may use it — Drupal autoloads
`Drupal\Tests\<module>\` from `<module>/tests/src`.

## Security

Only ASCII digits are accepted. Arabic-Indic digits (`١٢٣٤٥٦٧٨٩٠`), zero-width
characters and non-breaking spaces are rejected rather than folded into ASCII —
a value that looks right but carries invisible characters is exactly the sort
that should not reach storage. Surrounding whitespace is trimmed, because that
is a copy-and-paste artefact; whitespace inside the number is not.

## Extending

`SaudiIdValidatorInterface` is what consumers inject, and the container binds it
to `SaudiIdValidator`. A site or a module may bind it to something else without
any consumer changing what it asks for. `LuhnChecksum` is a separate class for
the same reason: a scheme with a different check digit is a new class beside it,
not an edit inside it.

## Versioning

Semantic versioning. The public API — everything marked `@api`, the service
names, the `SaudiId` constraint and its options, the configuration object, and
the numbers the test generator produces — will not change incompatibly before
2.0.0. Classes marked `@internal` may change in any release. See
[API.md](API.md#stability-contract) for the full contract.

## Licence

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
