<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Checksum;

/**
 * The Luhn check used by Saudi identification numbers.
 *
 * Kept apart from the validator so the arithmetic can be tested on its own,
 * without a container, and so a future scheme with a different check digit can
 * be added beside it rather than inside it.
 *
 * The rule, over the ten digits left to right: every digit in an odd position
 * is doubled and, when that exceeds nine, replaced by the sum of its own two
 * digits; digits in even positions are added as they stand. A number is well
 * formed when the total is a multiple of ten.
 *
 * @api
 *   Public and stable since 1.0.0. Exposed because the test generator takes
 *   one; day-to-day validation should go through SaudiIdValidatorInterface.
 */
final class LuhnChecksum {

  /**
   * The number of digits a Saudi identification number has.
   */
  public const LENGTH = 10;

  /**
   * The multiplier applied to digits in odd positions.
   */
  private const DOUBLING_FACTOR = 2;

  /**
   * The largest value a digit may reach before its digits are summed.
   */
  private const MAX_SINGLE_DIGIT = 9;

  /**
   * The modulus the final total must satisfy.
   */
  private const MODULUS = 10;

  /**
   * Whether the ten digits carry a correct check digit.
   *
   * @param string $digits
   *   Exactly ten ASCII digits. Anything else returns FALSE rather than
   *   throwing: the caller has already established the format, and a checksum
   *   question about a malformed string has no meaningful answer.
   *
   * @return bool
   *   TRUE when the checksum holds.
   */
  public function isValid(string $digits): bool {
    if (!$this->isTenDigits($digits)) {
      return FALSE;
    }

    return $this->sum($digits) % self::MODULUS === 0;
  }

  /**
   * The check digit that completes a nine-digit prefix.
   *
   * The tenth position is an even one, so it enters the total untouched and the
   * digit that balances it can be read straight off the running sum. Used by
   * the test data generator to mint numbers that satisfy the rule above.
   *
   * @param string $prefix
   *   Exactly nine ASCII digits.
   *
   * @return int
   *   The digit, 0-9, that makes the total a multiple of the modulus.
   *
   * @throws \InvalidArgumentException
   *   When the prefix is not nine digits.
   */
  public function checkDigit(string $prefix): int {
    if (preg_match('/^\d{' . (self::LENGTH - 1) . '}$/', $prefix) !== 1) {
      throw new \InvalidArgumentException(sprintf('A check digit needs a %d-digit prefix.', self::LENGTH - 1));
    }

    $remainder = $this->sum($prefix) % self::MODULUS;

    return (self::MODULUS - $remainder) % self::MODULUS;
  }

  /**
   * The weighted total of the digits given.
   *
   * @param string $digits
   *   ASCII digits, of any length: positions are counted from the left, so a
   *   nine-digit prefix weighs exactly as it will in the finished number.
   *
   * @return int
   *   The total.
   */
  private function sum(string $digits): int {
    $total = 0;
    $length = strlen($digits);

    for ($index = 0; $index < $length; $index++) {
      $digit = (int) $digits[$index];

      // Index 0 is position 1, so even indexes are the odd positions that get
      // doubled.
      if ($index % 2 === 0) {
        $digit *= self::DOUBLING_FACTOR;

        if ($digit > self::MAX_SINGLE_DIGIT) {
          // Subtracting nine is the sum of the two digits: the product is never
          // more than eighteen, so its tens digit is always one.
          $digit -= self::MAX_SINGLE_DIGIT;
        }
      }

      $total += $digit;
    }

    return $total;
  }

  /**
   * Whether the string is exactly ten ASCII digits.
   *
   * @param string $digits
   *   The candidate.
   *
   * @return bool
   *   TRUE when it is.
   */
  private function isTenDigits(string $digits): bool {
    return preg_match('/^\d{' . self::LENGTH . '}$/', $digits) === 1;
  }

}
