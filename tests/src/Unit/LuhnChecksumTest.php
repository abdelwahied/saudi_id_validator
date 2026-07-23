<?php

declare(strict_types=1);

namespace Drupal\Tests\saudi_id_validator\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\saudi_id_validator\Checksum\LuhnChecksum;
use Drupal\saudi_id_validator\IdType;
use Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the check-digit arithmetic on its own.
 */
#[Group('saudi_id_validator')]
#[CoversMethod(LuhnChecksum::class, 'checkDigit')]
#[CoversMethod(LuhnChecksum::class, 'isValid')]
final class LuhnChecksumTest extends UnitTestCase {

  /**
   * The checksum under test.
   */
  private LuhnChecksum $checksum;

  /**
   * The generator producing its inputs.
   */
  private SaudiIdGenerator $generator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->checksum = new LuhnChecksum();
    $this->generator = new SaudiIdGenerator($this->checksum);
  }

  /**
   * A generated check digit always completes its own prefix.
   */
  public function testGeneratedNumbersSatisfyTheChecksum(): void {
    foreach ([IdType::SaudiNationalId, IdType::Iqama] as $type) {
      foreach ($this->generator->many($type, 200) as $id) {
        self::assertTrue($this->checksum->isValid($id), sprintf('%s should satisfy the checksum.', $id));
      }
    }
  }

  /**
   * Moving any single digit breaks the total.
   */
  public function testSingleAlteredDigitFails(): void {
    $id = $this->generator->nationalId(42);

    for ($position = 0; $position < LuhnChecksum::LENGTH; $position++) {
      $altered = $id;
      $altered[$position] = (string) (((int) $id[$position] + 1) % 10);

      self::assertFalse(
        $this->checksum->isValid($altered),
        sprintf('Altering position %d of %s should break the checksum.', $position, $id),
      );
    }
  }

  /**
   * Anything but ten digits is rejected rather than computed.
   */
  public function testMalformedInputIsRejected(): void {
    foreach ($this->generator->malformedSamples() as $case => $value) {
      self::assertFalse($this->checksum->isValid($value), sprintf('The %s case should be rejected.', $case));
    }

    foreach ([1, 5, 9, 11, 20] as $length) {
      self::assertFalse($this->checksum->isValid($this->generator->wrongLength($length)));
    }
  }

  /**
   * A prefix that is not nine digits cannot yield a check digit.
   */
  public function testCheckDigitRefusesWrongPrefix(): void {
    $this->expectException(\InvalidArgumentException::class);

    $this->checksum->checkDigit('12345');
  }

  /**
   * Every check digit produced is a single digit.
   */
  public function testCheckDigitIsAlwaysOneDigit(): void {
    foreach (range(0, 99) as $sequence) {
      $prefix = substr($this->generator->nationalId($sequence), 0, LuhnChecksum::LENGTH - 1);
      $digit = $this->checksum->checkDigit($prefix);

      self::assertGreaterThanOrEqual(0, $digit);
      self::assertLessThanOrEqual(9, $digit);
    }
  }

}
