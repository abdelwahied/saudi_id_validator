<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Why a candidate identification number was rejected.
 *
 * Carried on the metadata so a caller can word its own message precisely — a
 * mistyped length and a failed check digit are different mistakes to the person
 * correcting them, and a single "invalid" tells them nothing.
 *
 * @api
 *   Public and stable since 1.0.0. A new case may be added in a minor release,
 *   so match() on it should always carry a default arm.
 */
enum FailureReason: string {

  // The value held something other than ten ASCII digits.
  case NotTenDigits = 'NOT_TEN_DIGITS';

  // The value was ten digits but began with neither 1 nor 2.
  case UnknownLeadingDigit = 'UNKNOWN_LEADING_DIGIT';

  // The value was shaped correctly but its check digit did not add up.
  case ChecksumFailed = 'CHECKSUM_FAILED';

  /**
   * The message explaining this rejection to the person who typed it.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  public function message(): TranslatableMarkup {
    return match ($this) {
      self::NotTenDigits => new TranslatableMarkup('The ID must contain exactly 10 digits.'),
      self::UnknownLeadingDigit => new TranslatableMarkup('Invalid Saudi ID number: it must start with 1 (National ID) or 2 (Iqama).'),
      self::ChecksumFailed => new TranslatableMarkup('Checksum validation failed. Please check the number you entered.'),
    };
  }

}
