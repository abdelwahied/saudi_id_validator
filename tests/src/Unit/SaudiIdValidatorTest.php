<?php

declare(strict_types=1);

namespace Drupal\Tests\saudi_id_validator\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\saudi_id_validator\Checksum\LuhnChecksum;
use Drupal\saudi_id_validator\Event\SaudiIdValidatedEvent;
use Drupal\saudi_id_validator\Event\SaudiIdValidationFailedEvent;
use Drupal\saudi_id_validator\FailureReason;
use Drupal\saudi_id_validator\IdType;
use Drupal\saudi_id_validator\SaudiIdValidator;
use Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Tests the validator service.
 */
#[Group('saudi_id_validator')]
#[CoversMethod(SaudiIdValidator::class, 'isValid')]
#[CoversMethod(SaudiIdValidator::class, 'detectType')]
#[CoversMethod(SaudiIdValidator::class, 'isSaudiCitizen')]
#[CoversMethod(SaudiIdValidator::class, 'isResident')]
#[CoversMethod(SaudiIdValidator::class, 'getMetadata')]
final class SaudiIdValidatorTest extends UnitTestCase {

  /**
   * The validator under test.
   */
  private SaudiIdValidator $validator;

  /**
   * The generator producing its inputs.
   */
  private SaudiIdGenerator $generator;

  /**
   * The dispatcher the validator announces through.
   */
  private EventDispatcher $dispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $checksum = new LuhnChecksum();
    $this->dispatcher = new EventDispatcher();
    $this->validator = new SaudiIdValidator($checksum, $this->dispatcher);
    $this->generator = new SaudiIdGenerator($checksum);
  }

  /**
   * Valid national identity numbers are accepted and typed.
   */
  public function testValidNationalIds(): void {
    foreach ($this->generator->many(IdType::SaudiNationalId, 100) as $id) {
      self::assertTrue($this->validator->isValid($id), sprintf('%s should be valid.', $id));
      self::assertSame(IdType::SaudiNationalId, $this->validator->detectType($id));
      self::assertTrue($this->validator->isSaudiCitizen($id));
      self::assertFalse($this->validator->isResident($id));
    }
  }

  /**
   * Valid Iqama numbers are accepted and typed.
   */
  public function testValidIqamaNumbers(): void {
    foreach ($this->generator->many(IdType::Iqama, 100) as $id) {
      self::assertTrue($this->validator->isValid($id), sprintf('%s should be valid.', $id));
      self::assertSame(IdType::Iqama, $this->validator->detectType($id));
      self::assertTrue($this->validator->isResident($id));
      self::assertFalse($this->validator->isSaudiCitizen($id));
    }
  }

  /**
   * A wrong check digit is reported as a checksum failure, not a bad format.
   */
  public function testWrongChecksumIsRejected(): void {
    foreach (range(0, 50) as $sequence) {
      $id = $this->generator->wrongChecksum($sequence);
      $metadata = $this->validator->getMetadata($id);

      self::assertFalse($metadata->valid, sprintf('%s should fail.', $id));
      self::assertSame(FailureReason::ChecksumFailed, $metadata->reason);
      self::assertNull($metadata->type);
    }
  }

  /**
   * A leading digit other than 1 or 2 is refused even with a sound checksum.
   */
  public function testUnknownLeadingDigitIsRejected(): void {
    foreach (range(0, 20) as $sequence) {
      $id = $this->generator->unknownLeadingDigit($sequence);
      $metadata = $this->validator->getMetadata($id);

      self::assertFalse($metadata->valid, sprintf('%s should fail.', $id));
      self::assertSame(FailureReason::UnknownLeadingDigit, $metadata->reason);
    }
  }

  /**
   * Wrong lengths are reported as a format failure.
   */
  public function testWrongLengthIsRejected(): void {
    foreach ([0, 1, 5, 9, 11, 12, 30] as $length) {
      $metadata = $this->validator->getMetadata($this->generator->wrongLength($length));

      self::assertFalse($metadata->valid, sprintf('A %d-digit value should fail.', $length));
      self::assertSame(FailureReason::NotTenDigits, $metadata->reason);
    }
  }

  /**
   * Letters, symbols, scripts and invisible characters are all refused.
   */
  public function testMalformedInputIsRejected(): void {
    foreach ($this->generator->malformedSamples() as $case => $value) {
      self::assertFalse($this->validator->isValid($value), sprintf('The %s case should be refused.', $case));
    }
  }

  /**
   * Surrounding whitespace is forgiven; whitespace inside is not.
   */
  public function testSurroundingWhitespaceIsTrimmed(): void {
    $id = $this->generator->nationalId(7);

    self::assertTrue($this->validator->isValid(' ' . $id . " \n"));
    self::assertFalse($this->validator->isValid(substr($id, 0, 5) . ' ' . substr($id, 5)));
  }

  /**
   * The metadata array reports the verdict for other modules.
   */
  public function testMetadataDescribesTheVerdict(): void {
    $id = $this->generator->iqama(3);
    $metadata = $this->validator->getMetadata($id)->toArray();

    self::assertTrue($metadata['valid']);
    self::assertSame(IdType::Iqama->value, $metadata['type']);
    self::assertSame('2', $metadata['first_digit']);
    self::assertNull($metadata['reason']);

    $failed = $this->validator->getMetadata($this->generator->wrongChecksum())->toArray();

    self::assertFalse($failed['valid']);
    self::assertNull($failed['type']);
    self::assertSame(FailureReason::ChecksumFailed->value, $failed['reason']);
  }

  /**
   * Subscribers hear about both outcomes.
   */
  public function testEventsAreDispatched(): void {
    $seen = [];

    $this->dispatcher->addListener(
      SaudiIdValidatedEvent::class,
      static function (SaudiIdValidatedEvent $event) use (&$seen): void {
        $seen['passed'] = $event->metadata->type;
      },
    );
    $this->dispatcher->addListener(
      SaudiIdValidationFailedEvent::class,
      static function (SaudiIdValidationFailedEvent $event) use (&$seen): void {
        $seen['failed'] = $event->metadata->reason;
      },
    );

    $this->validator->isValid($this->generator->nationalId(11));
    $this->validator->isValid($this->generator->wrongChecksum(11));

    self::assertSame(IdType::SaudiNationalId, $seen['passed'] ?? NULL);
    self::assertSame(FailureReason::ChecksumFailed, $seen['failed'] ?? NULL);
  }

  /**
   * The event carries the value exactly as it was submitted.
   */
  public function testEventCarriesTheOriginalValue(): void {
    $submitted = '  ' . $this->generator->nationalId(5) . '  ';
    $original = NULL;

    $this->dispatcher->addListener(
      SaudiIdValidatedEvent::class,
      static function (SaudiIdValidatedEvent $event) use (&$original): void {
        $original = $event->originalValue;
      },
    );

    $this->validator->isValid($submitted);

    self::assertSame($submitted, $original);
  }

}
