# Public API reference

Everything on this page is covered by semantic versioning: it will not change
incompatibly before 2.0.0. Anything not listed here — in particular any class
whose docblock says `@internal` — may change in any release.

**Version 1.0.0 establishes this initial public API contract.** From 1.0.0
onward, no `@api` class, interface, service ID, plugin ID, event name or
documented configuration key is removed or changed incompatibly except in a new
major version, as described in [UPGRADING.md](UPGRADING.md).

For a task-oriented introduction, read the [README](README.md). This page is the
reference.

## Contents

- [Stability contract](#stability-contract)
- [Service: SaudiIdValidatorInterface](#service-saudiidvalidatorinterface)
- [Value: IdMetadata](#value-idmetadata)
- [Enum: IdType](#enum-idtype)
- [Enum: FailureReason](#enum-failurereason)
- [Checksum: LuhnChecksum](#checksum-luhnchecksum)
- [Form API: SaudiIdElementValidator](#form-api-saudiidelementvalidator)
- [Constraint: SaudiId](#constraint-saudiid)
- [Events](#events)
- [Settings: ValidatorSettings](#settings-validatorsettings)
- [Configuration object](#configuration-object)
- [Test API: SaudiIdGenerator](#test-api-saudiidgenerator)
- [What is internal](#what-is-internal)

## Stability contract

The public API is:

| Surface | Identifier |
| --- | --- |
| Service (by interface) | `Drupal\saudi_id_validator\SaudiIdValidatorInterface` |
| Service (by name) | `saudi_id_validator.validator` |
| Settings service | `saudi_id_validator.settings` |
| Constraint plugin | `SaudiId`, option `requireType` |
| Configuration | `saudi_id_validator.settings` |
| Route | `saudi_id_validator.settings` |
| Permission | `administer saudi id validator` |
| Events | `SaudiIdValidatedEvent`, `SaudiIdValidationFailedEvent` |
| Test generator | `Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator` |

Plus every class marked `@api` in its docblock.

One nuance worth knowing before you write a `match()`: **`IdType` cases will not
change** within a major version — adding one would break exhaustive matches. But
**`FailureReason` may gain a case in a minor release**, because a new way for a
number to be wrong is not a new kind of number. Always give a `match()` on
`FailureReason` a default arm.

## Service: SaudiIdValidatorInterface

The one place any part of a site decides whether a number is well formed. Inject
it; never call it statically, and never inject the implementing class.

```php
namespace Drupal\my_module;

use Drupal\saudi_id_validator\SaudiIdValidatorInterface;

final class ApplicantChecker {

  public function __construct(
    private readonly SaudiIdValidatorInterface $saudiIds,
  ) {}

}
```

```yaml
# my_module.services.yml
services:
  Drupal\my_module\ApplicantChecker:
    arguments:
      - '@Drupal\saudi_id_validator\SaudiIdValidatorInterface'
```

### `isValid(string $id): bool`

Whether the value passes all three rules.

```php
$this->saudiIds->isValid('1000000008');   // TRUE
$this->saudiIds->isValid('1234567890');   // FALSE — check digit fails
$this->saudiIds->isValid('  1000000008 ') // TRUE — surrounding space is trimmed
```

### `detectType(string $id): ?IdType`

The type of a valid number, or `NULL` when the number is not valid at all. An
invalid number has no type — do not read a `NULL` as "some other kind".

```php
$type = $this->saudiIds->detectType($id);

if ($type === NULL) {
  // Not a usable number. Ask getMetadata() why.
}
```

### `isSaudiCitizen(string $id): bool`

`TRUE` only for a valid National ID. Both a valid Iqama and an invalid number
return `FALSE`, so this is the right question for "may this applicant do the
thing only citizens may do".

### `isResident(string $id): bool`

`TRUE` only for a valid Iqama.

### `getMetadata(string $id): IdMetadata`

The full verdict, including why an invalid number failed. Prefer this when you
need to tell the user something; the boolean methods are shorthands over it.

```php
$verdict = $this->saudiIds->getMetadata($id);

if (!$verdict->valid) {
  $form_state->setErrorByName('national_id', $verdict->reason->message());

  return;
}
```

Each public call dispatches exactly one event, whichever method you used.

## Value: IdMetadata

Immutable. You never construct one; the validator does.

| Member | Type | Meaning |
| --- | --- | --- |
| `$valid` | `bool` | Passed every rule |
| `$type` | `?IdType` | The type, or `NULL` when invalid |
| `$reason` | `?FailureReason` | Why it failed, or `NULL` when it did not |
| `$normalised` | `string` | The value as read, trimmed |
| `firstDigit()` | `?string` | The leading digit, or `NULL` when nothing usable |
| `toArray()` | `array` | The verdict as a plain array |

```php
$verdict->toArray();
// [
//   'valid' => TRUE,
//   'type' => 'IQAMA',
//   'label' => TranslatableMarkup('Resident ID (Iqama)'),
//   'first_digit' => '2',
//   'reason' => NULL,
// ]
```

`label` is a `TranslatableMarkup`, not a string, on purpose: casting it when the
verdict is reached would freeze it in whatever language happened to be active
then. Cast it where you display it.

## Enum: IdType

```php
use Drupal\saudi_id_validator\IdType;

IdType::SaudiNationalId;                 // backing value 'SAUDI_NATIONAL_ID'
IdType::Iqama;                           // backing value 'IQAMA'

IdType::SaudiNationalId->leadingDigit(); // '1'
IdType::Iqama->leadingDigit();           // '2'
IdType::Iqama->label();                  // TranslatableMarkup
IdType::fromLeadingDigit('2');           // IdType::Iqama
IdType::fromLeadingDigit('3');           // NULL
```

## Enum: FailureReason

```php
use Drupal\saudi_id_validator\FailureReason;

FailureReason::NotTenDigits;          // 'NOT_TEN_DIGITS'
FailureReason::UnknownLeadingDigit;   // 'UNKNOWN_LEADING_DIGIT'
FailureReason::ChecksumFailed;        // 'CHECKSUM_FAILED'

$reason->message();                   // the translated message for the user
```

The reasons are checked in that order, so the message names the mistake actually
made: someone who typed eleven digits is told about the length, not the checksum.

Give any `match()` on this a default arm — a case may be added in a minor
release.

## Checksum: LuhnChecksum

The arithmetic on its own, with no container and no dependencies. You rarely need
it directly; validate through the service instead.

```php
use Drupal\saudi_id_validator\Checksum\LuhnChecksum;

$checksum = new LuhnChecksum();

LuhnChecksum::LENGTH;                    // 10
$checksum->isValid('1000000008');        // TRUE
$checksum->checkDigit('100000000');      // 8 — the digit completing a 9-digit prefix
```

`checkDigit()` throws `\InvalidArgumentException` if the prefix is not exactly
nine digits.

## Form API: SaudiIdElementValidator

```php
use Drupal\saudi_id_validator\Validator\SaudiIdElementValidator;

$form['national_id'] = [
  '#type' => 'textfield',
  '#title' => $this->t('National ID'),
  '#maxlength' => 10,
  '#element_validate' => [
    [SaudiIdElementValidator::class, 'validate'],
  ],
];
```

An empty value is left alone. Whether the field is mandatory is `#required`'s
business — answering it here as well would make an optional field impossible.

The callback is static because the Form API stores it in the form array and
calls it without an object. It holds no rules; it asks the service.

## Constraint: SaudiId

Attach it to a field definition and every path into the entity is covered at
once — entity forms, JSON:API, REST, migrations, and code that saves
programmatically — because they all run the entity's own constraints.

```php
$fields['national_id'] = BaseFieldDefinition::create('string')
  ->setLabel(t('National ID'))
  ->setSetting('max_length', 32)
  ->addConstraint('SaudiId');
```

Restricting the field to one type:

```php
use Drupal\saudi_id_validator\IdType;

$fields['national_id']->addConstraint('SaudiId', [
  'requireType' => IdType::SaudiNationalId->value,
]);
```

An invalid value submitted through JSON:API returns a standard `422` carrying
the violation message. No custom endpoint is involved.

Passing an option the constraint does not define throws
`\InvalidArgumentException` rather than silently creating a property, so a typo
is a loud failure.

An empty value raises no violation — that is `NotNull`'s job, and a field may
legitimately be optional and, when filled, valid.

## Events

Both carry the same two readonly properties: `$originalValue`, the value exactly
as submitted, and `$metadata`, the verdict.

```php
namespace Drupal\my_module\EventSubscriber;

use Drupal\saudi_id_validator\Event\SaudiIdValidatedEvent;
use Drupal\saudi_id_validator\Event\SaudiIdValidationFailedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class IdAudit implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      SaudiIdValidatedEvent::class => 'onValidated',
      SaudiIdValidationFailedEvent::class => 'onFailed',
    ];
  }

  public function onValidated(SaudiIdValidatedEvent $event): void {
    // $event->metadata->type is an IdType.
  }

  public function onFailed(SaudiIdValidationFailedEvent $event): void {
    // $event->metadata->reason says which rule was broken.
  }

}
```

Subscribers observe. The verdict is already decided when the event is sent and
cannot be changed by a listener — that is what keeps one validator authoritative.

`$originalValue` is raw user input. Escape it if you render it.

## Settings: ValidatorSettings

```php
use Drupal\saudi_id_validator\ValidatorSettings;

$settings->automaticValidationEnabled();  // bool
$settings->watchedFieldNames();           // array<int, string>
$settings->watches('national_id');        // bool
$settings->showDetectedType();            // bool

ValidatorSettings::CONFIG_NAME;           // 'saudi_id_validator.settings'
ValidatorSettings::DEFAULT_FIELD_NAMES;   // the shipped list
```

Values are read through the config factory on every call, so a change on the
settings form takes effect at once.

There is deliberately no method that relaxes a rule. What is configurable is
*where* validation is applied and *what the user is told* — never whether a
number that fails is nonetheless accepted.

## Configuration object

`saudi_id_validator.settings`:

| Key | Type | Default | Meaning |
| --- | --- | --- | --- |
| `automatic_validation` | boolean | `true` | Validate matching fields without code |
| `field_names` | sequence of string | see below | Machine names watched |
| `show_detected_type` | boolean | `false` | Show the detected type as a status message |

Shipped `field_names`: `national_id`, `saudi_id`, `identity_number`, `iqama`,
`id_number`.

`automatic_validation` controls where validation is *added*, never where it is
*skipped*: a field that attaches the validator or the constraint itself is always
validated, whatever this setting says.

## Test API: SaudiIdGenerator

Ships under `tests/`, so it never loads in production. Any module's tests may use
it — Drupal autoloads `Drupal\Tests\<module>\` from `<module>/tests/src`.

```php
use Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator;
use Drupal\saudi_id_validator\IdType;

$ids = new SaudiIdGenerator();
```

| Method | Returns |
| --- | --- |
| `nationalId(int $sequence = 0)` | Valid, leading digit 1 |
| `iqama(int $sequence = 0)` | Valid, leading digit 2 |
| `valid(IdType $type, int $sequence = 0)` | Valid, of that type |
| `many(IdType $type, int $count)` | `$count` distinct valid numbers |
| `wrongChecksum(int $sequence = 0)` | Right shape, wrong check digit |
| `unknownLeadingDigit(int $sequence = 0)` | Right checksum, leading digit not 1 or 2 |
| `wrongLength(int $length)` | Exactly `$length` digits |
| `malformedSamples()` | Letters, symbols, scripts, Arabic-Indic digits, zero-width and non-breaking spaces |
| `randomValid(IdType $type)` | Valid, drawn at random |

Numbers are derived from a sequence, not drawn at random, so the same argument
always yields the same number and a failing test reproduces exactly. Use
`randomValid()` only where breadth matters more than repeatability.

`wrongLength(10)` throws `\InvalidArgumentException` — asking a "wrong length"
helper for the correct length is a programming mistake, not a silent pass.

**Never write a ten-digit literal in a test.** Nobody can tell by eye whether it
satisfies the check digit, and one that fails it makes the test pass for the
wrong reason.

## What is internal

These are implementation details. They are not covered by the version promise and
may change in any release:

| Class | Use instead |
| --- | --- |
| `SaudiIdValidator` | `SaudiIdValidatorInterface` |
| `SaudiIdConstraintValidator` | the `SaudiId` constraint |
| `Form\SettingsForm` | the route and `ValidatorSettings` |
