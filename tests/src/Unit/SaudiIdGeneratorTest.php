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
 * Tests the test data generator.
 *
 * The generator underpins every other test's inputs, so it is held to the same
 * standard as the code it feeds: if it ever produced a number that failed the
 * checksum, the suites relying on it would report a false pass.
 */
#[Group('saudi_id_validator')]
#[CoversMethod(SaudiIdGenerator::class, 'valid')]
#[CoversMethod(SaudiIdGenerator::class, 'nationalId')]
#[CoversMethod(SaudiIdGenerator::class, 'iqama')]
#[CoversMethod(SaudiIdGenerator::class, 'many')]
#[CoversMethod(SaudiIdGenerator::class, 'wrongChecksum')]
#[CoversMethod(SaudiIdGenerator::class, 'unknownLeadingDigit')]
#[CoversMethod(SaudiIdGenerator::class, 'wrongLength')]
#[CoversMethod(SaudiIdGenerator::class, 'randomValid')]
#[CoversMethod(SaudiIdGenerator::class, 'malformedSamples')]
final class SaudiIdGeneratorTest extends UnitTestCase {

  /**
   * The generator under test.
   */
  private SaudiIdGenerator $generator;

  /**
   * The arithmetic its output must satisfy.
   */
  private LuhnChecksum $checksum;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->checksum = new LuhnChecksum();
    $this->generator = new SaudiIdGenerator($this->checksum);
  }

  /**
   * Every generated number is ten digits with the right leading digit.
   */
  public function testValidNumbersAreWellFormed(): void {
    foreach ([IdType::SaudiNationalId, IdType::Iqama] as $type) {
      foreach ($this->generator->many($type, 250) as $id) {
        self::assertMatchesRegularExpression('/^\d{10}$/', $id);
        self::assertSame($type->leadingDigit(), $id[0]);
        self::assertTrue($this->checksum->isValid($id));
      }
    }
  }

  /**
   * The same sequence always yields the same number.
   */
  public function testGenerationIsDeterministic(): void {
    foreach (range(0, 20) as $sequence) {
      self::assertSame(
        $this->generator->nationalId($sequence),
        (new SaudiIdGenerator())->nationalId($sequence),
        'A sequence must reproduce across generator instances.',
      );
    }
  }

  /**
   * Different sequences yield different numbers.
   */
  public function testSequencesDoNotCollide(): void {
    $ids = $this->generator->many(IdType::SaudiNationalId, 500);

    self::assertCount(500, array_unique($ids));
  }

  /**
   * The deliberately broken numbers really are broken, one reason each.
   */
  public function testInvalidNumbersFailForTheStatedReason(): void {
    foreach (range(0, 30) as $sequence) {
      $wrongChecksum = $this->generator->wrongChecksum($sequence);

      self::assertMatchesRegularExpression('/^[12]\d{9}$/', $wrongChecksum, 'Only the check digit should be wrong.');
      self::assertFalse($this->checksum->isValid($wrongChecksum));

      $unknownPrefix = $this->generator->unknownLeadingDigit($sequence);

      self::assertNotContains($unknownPrefix[0], ['1', '2']);
      self::assertTrue($this->checksum->isValid($unknownPrefix), 'Only the leading digit should be wrong.');
    }

    foreach ([1, 9, 11, 25] as $length) {
      self::assertSame($length, strlen($this->generator->wrongLength($length)));
    }
  }

  /**
   * Asking for a correct length is a programming mistake, not a silent pass.
   */
  public function testWrongLengthRefusesTheCorrectLength(): void {
    $this->expectException(\InvalidArgumentException::class);

    $this->generator->wrongLength(LuhnChecksum::LENGTH);
  }

  /**
   * Random numbers are valid too, whichever ones come up.
   */
  public function testRandomNumbersAreValid(): void {
    for ($attempt = 0; $attempt < 100; $attempt++) {
      self::assertTrue($this->checksum->isValid($this->generator->randomValid(IdType::SaudiNationalId)));
      self::assertTrue($this->checksum->isValid($this->generator->randomValid(IdType::Iqama)));
    }
  }

  /**
   * The malformed samples are all genuinely malformed.
   */
  public function testMalformedSamplesAreMalformed(): void {
    foreach ($this->generator->malformedSamples() as $case => $value) {
      self::assertDoesNotMatchRegularExpression(
        '/^\d{10}$/',
        $value,
        sprintf('The %s sample must not look like a well-formed number.', $case),
      );
    }
  }

}
