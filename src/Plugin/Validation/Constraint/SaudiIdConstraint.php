<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintPlugin;
use Symfony\Component\Validator\Constraint;

/**
 * Requires a field's value to be a well-formed Saudi identification number.
 *
 * Attach it to any field definition and every path into the entity is covered
 * at once — entity forms, JSON:API, REST, migrations and code that saves
 * programmatically — because they all run the entity's own constraints:
 * @code
 * $fields['national_id']->addConstraint('SaudiId');
 * @endcode
 *
 * To insist on one type, name it:
 * @code
 * $fields['national_id']->addConstraint('SaudiId', [
 *   'requireType' => IdType::SaudiNationalId->value,
 * ]);
 * @endcode
 *
 * The rejection message is not configurable per field on purpose: the reason a
 * number failed is decided by the validator, and each reason already carries
 * the wording that explains it.
 *
 * @api
 *   Public and stable since 1.0.0. The plugin id `SaudiId` and the
 *   `requireType` option are the contract.
 */
#[ConstraintPlugin(
  id: 'SaudiId',
  label: new TranslatableMarkup('Saudi identification number', [], ['context' => 'Validation']),
)]
final class SaudiIdConstraint extends Constraint {

  /**
   * Restricts the field to one type of number.
   *
   * NULL accepts either. Set it to a case value of
   * \Drupal\saudi_id_validator\IdType to insist on one — a field that may only
   * hold a citizen's number sets `SAUDI_NATIONAL_ID`.
   *
   * @var string|null
   */
  public ?string $requireType = NULL;

  /**
   * The message shown when a number is valid but of the wrong type.
   *
   * @var string
   */
  public string $wrongTypeMessage = 'This must be a @expected. @actual numbers are not accepted here.';

  /**
   * Constructs a SaudiIdConstraint.
   *
   * Options are unpacked here rather than left to the base class: passing an
   * array up to it is deprecated as of symfony/validator 7.4, and only a NULL
   * takes the path that skips the deprecation. Drupal's constraint manager
   * always hands plugins an array, so the unpacking has to happen somewhere,
   * and doing it explicitly means an unknown option is a loud mistake rather
   * than a silently created property.
   *
   * @param array<string, mixed>|null $options
   *   The plugin options, as Drupal's constraint manager passes them.
   * @param string|null $requireType
   *   The one accepted type, when named directly.
   * @param array<int, string>|null $groups
   *   The validation groups.
   * @param mixed $payload
   *   Arbitrary domain data attached by the caller.
   *
   * @throws \InvalidArgumentException
   *   When an option this constraint does not define is passed.
   */
  public function __construct(
    ?array $options = NULL,
    ?string $requireType = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    $options ??= [];

    $requireType ??= isset($options['requireType']) ? (string) $options['requireType'] : NULL;
    $groups ??= isset($options['groups']) ? (array) $options['groups'] : NULL;
    $payload ??= $options['payload'] ?? NULL;

    $unknown = array_diff(array_keys($options), ['requireType', 'groups', 'payload']);

    if ($unknown !== []) {
      throw new \InvalidArgumentException(sprintf('The SaudiId constraint does not define the option(s): %s.', implode(', ', $unknown)));
    }

    parent::__construct(NULL, $groups, $payload);

    $this->requireType = $requireType ?? $this->requireType;
  }

}
