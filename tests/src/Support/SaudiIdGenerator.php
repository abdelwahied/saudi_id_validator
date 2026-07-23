<?php

declare(strict_types=1);

namespace Drupal\Tests\saudi_id_validator\Support;

use Drupal\saudi_id_validator\Checksum\LuhnChecksum;
use Drupal\saudi_id_validator\IdType;

/**
 * Mints identification numbers for tests, valid and invalid on purpose.
 *
 * Exists so no test has to paste a literal ten-digit number. A hardcoded ID is
 * a small landmine: it is impossible to tell by eye whether it satisfies the
 * check digit, and the day the rules tighten every one of them has to be
 * re-derived by hand. Asking for `nationalId()` instead says what the test
 * means and stays correct by construction.
 *
 * Numbers are derived from a sequence rather than drawn at random, so a failing
 * test reproduces exactly. The `random*` methods exist for fuzzing, where the
 * point is breadth rather than repeatability.
 *
 * Available to any module's tests: Drupal autoloads `Drupal\Tests\<module>\`
 * from `<module>/tests/src`, so other test suites may use this one.
 *
 * @api
 *   Public and stable since 1.0.0. Other modules' test suites may rely on
 *   it; a generated number's value for a given sequence will not change
 *   within a major version.
 */
final class SaudiIdGenerator {

  /**
   * The number of digits between the leading digit and the check digit.
   */
  private const BODY_LENGTH = LuhnChecksum::LENGTH - 2;

  /**
   * Leading digits that denote no type at all.
   */
  private const UNKNOWN_LEADING_DIGITS = ['0', '3', '4', '5', '6', '7', '8', '9'];

  /**
   * Constructs a SaudiIdGenerator.
   *
   * @param \Drupal\saudi_id_validator\Checksum\LuhnChecksum|null $checksum
   *   The checksum to satisfy. Defaults to the module's own, so a generated
   *   number is valid by the same arithmetic the code under test uses — the two
   *   can never drift apart.
   */
  public function __construct(private readonly LuhnChecksum $checksum = new LuhnChecksum()) {}

  /**
   * A valid Saudi national identity number.
   *
   * @param int $sequence
   *   Which number in the series to produce. The same sequence always yields
   *   the same number.
   *
   * @return string
   *   Ten digits beginning with 1, with a correct check digit.
   */
  public function nationalId(int $sequence = 0): string {
    return $this->valid(IdType::SaudiNationalId, $sequence);
  }

  /**
   * A valid Iqama number.
   *
   * @param int $sequence
   *   Which number in the series to produce.
   *
   * @return string
   *   Ten digits beginning with 2, with a correct check digit.
   */
  public function iqama(int $sequence = 0): string {
    return $this->valid(IdType::Iqama, $sequence);
  }

  /**
   * A valid number of the given type.
   *
   * @param \Drupal\saudi_id_validator\IdType $type
   *   The type wanted.
   * @param int $sequence
   *   Which number in the series to produce.
   *
   * @return string
   *   The number.
   */
  public function valid(IdType $type, int $sequence = 0): string {
    $prefix = $type->leadingDigit() . $this->body($sequence);

    return $prefix . $this->checksum->checkDigit($prefix);
  }

  /**
   * Several valid numbers of one type.
   *
   * @param \Drupal\saudi_id_validator\IdType $type
   *   The type wanted.
   * @param int $count
   *   How many.
   *
   * @return array<int, string>
   *   The numbers, all distinct.
   */
  public function many(IdType $type, int $count): array {
    return array_map(fn (int $sequence): string => $this->valid($type, $sequence), range(0, $count - 1));
  }

  /**
   * A correctly shaped number whose check digit is wrong.
   *
   * @param int $sequence
   *   Which number in the series to spoil.
   *
   * @return string
   *   Ten digits, right leading digit, failing checksum.
   */
  public function wrongChecksum(int $sequence = 0): string {
    $valid = $this->nationalId($sequence);
    $last = (int) $valid[LuhnChecksum::LENGTH - 1];

    // Moving the final digit by one moves the total by one, which no multiple
    // of ten survives.
    return substr($valid, 0, LuhnChecksum::LENGTH - 1) . (($last + 1) % 10);
  }

  /**
   * Ten digits that begin with something other than 1 or 2.
   *
   * @param int $sequence
   *   Which of the unusable leading digits to use, cycled.
   *
   * @return string
   *   The number.
   */
  public function unknownLeadingDigit(int $sequence = 0): string {
    $digit = self::UNKNOWN_LEADING_DIGITS[$sequence % count(self::UNKNOWN_LEADING_DIGITS)];
    $prefix = $digit . $this->body($sequence);

    // Given a correct check digit, so the test proves the leading digit alone
    // was the reason for the rejection.
    return $prefix . $this->checksum->checkDigit($prefix);
  }

  /**
   * A number with the wrong number of digits.
   *
   * @param int $length
   *   How many digits to produce. Anything but ten.
   *
   * @return string
   *   The digits.
   *
   * @throws \InvalidArgumentException
   *   When asked for a valid length, which would defeat the purpose.
   */
  public function wrongLength(int $length): string {
    if ($length === LuhnChecksum::LENGTH) {
      throw new \InvalidArgumentException('wrongLength() must not be asked for the correct length.');
    }

    if ($length <= 0) {
      return '';
    }

    return substr(str_repeat($this->nationalId(), (int) ceil($length / LuhnChecksum::LENGTH)), 0, $length);
  }

  /**
   * Values that are not identification numbers at all.
   *
   * Covers the shapes the module promises to reject: letters, symbols, script
   * payloads, Arabic-Indic digits, and the whitespace and zero-width characters
   * that make a wrong value look right.
   *
   * @return array<string, string>
   *   Keyed by what each case is, for a readable data provider.
   */
  public function malformedSamples(): array {
    return [
      'empty' => '',
      'spaces only' => '   ',
      'letters' => 'abcdefghij',
      'letters and digits' => '1234abcd90',
      'symbols' => '1234-56789',
      'script payload' => '<script>alert(1)</script>',
      'sql fragment' => "1' OR '1'='1",
      'arabic-indic digits' => '١٢٣٤٥٦٧٨٩٠',
      'inner space' => '12345 67890',
      'tab' => "12345\t67890",
      'newline' => "1234567890\n1234567890",
      'zero-width space' => "123456789\u{200B}0",
      'non-breaking space' => "123456789\u{00A0}",
      'leading plus' => '+123456789',
      'float' => '1234567.89',
    ];
  }

  /**
   * A valid number drawn at random, for fuzzing.
   *
   * @param \Drupal\saudi_id_validator\IdType $type
   *   The type wanted.
   *
   * @return string
   *   The number.
   */
  public function randomValid(IdType $type): string {
    return $this->valid($type, random_int(0, 10 ** self::BODY_LENGTH - 1));
  }

  /**
   * The eight digits between the leading digit and the check digit.
   *
   * @param int $sequence
   *   The series position; wrapped so any integer is usable.
   *
   * @return string
   *   Exactly eight digits.
   */
  private function body(int $sequence): string {
    $modulus = 10 ** self::BODY_LENGTH;

    return str_pad((string) (abs($sequence) % $modulus), self::BODY_LENGTH, '0', STR_PAD_LEFT);
  }

}
