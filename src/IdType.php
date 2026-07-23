<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The kind of identification number, decided by its leading digit.
 *
 * An enum rather than string constants so a caller cannot invent a third kind
 * by typo, and so the leading digit and the human label live next to the case
 * they belong to instead of in a lookup table somewhere else.
 *
 * @api
 *   Public and stable since 1.0.0. Cases and their backing values are part of
 *   the contract; a new case would be a breaking change.
 */
enum IdType: string {

  // A citizen's national identity number.
  case SaudiNationalId = 'SAUDI_NATIONAL_ID';

  // A resident's permit (Iqama) number.
  case Iqama = 'IQAMA';

  /**
   * The type a leading digit denotes, if any.
   *
   * @param string $digit
   *   A single character.
   *
   * @return self|null
   *   The type, or NULL when no type uses that leading digit.
   */
  public static function fromLeadingDigit(string $digit): ?self {
    foreach (self::cases() as $case) {
      if ($case->leadingDigit() === $digit) {
        return $case;
      }
    }

    return NULL;
  }

  /**
   * The digit every number of this type begins with.
   *
   * @return string
   *   The leading digit.
   */
  public function leadingDigit(): string {
    return match ($this) {
      self::SaudiNationalId => '1',
      self::Iqama => '2',
    };
  }

  /**
   * The translated name of this type, for showing to a user.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::SaudiNationalId => new TranslatableMarkup('Saudi National ID'),
      self::Iqama => new TranslatableMarkup('Resident ID (Iqama)'),
    };
  }

}
