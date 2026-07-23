<?php

declare(strict_types=1);

namespace Drupal\Tests\saudi_id_validator\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\saudi_id_validator\Checksum\LuhnChecksum;
use Drupal\saudi_id_validator\IdType;
use Drupal\saudi_id_validator\SaudiIdValidator;
use Drupal\saudi_id_validator\SaudiIdValidatorInterface;
use Drupal\saudi_id_validator\ValidatorSettings;
use Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the container wires the module up as documented.
 */
#[RunTestsInSeparateProcesses]
#[Group('saudi_id_validator')]
final class SaudiIdValidatorServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['saudi_id_validator'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['saudi_id_validator']);
  }

  /**
   * The interface resolves to the implementation, by both documented names.
   */
  public function testServiceRegistration(): void {
    $byInterface = $this->container->get(SaudiIdValidatorInterface::class);
    $byAlias = $this->container->get('saudi_id_validator.validator');

    self::assertInstanceOf(SaudiIdValidator::class, $byInterface);
    self::assertSame($byInterface, $byAlias, 'The alias must resolve to the same instance.');
    self::assertInstanceOf(ValidatorSettings::class, $this->container->get('saudi_id_validator.settings'));
  }

  /**
   * The injected validator answers correctly through the container.
   */
  public function testDependencyInjectionWorks(): void {
    /** @var \Drupal\saudi_id_validator\SaudiIdValidatorInterface $validator */
    $validator = $this->container->get(SaudiIdValidatorInterface::class);
    $generator = new SaudiIdGenerator($this->container->get(LuhnChecksum::class));

    self::assertTrue($validator->isValid($generator->nationalId()));
    self::assertTrue($validator->isSaudiCitizen($generator->nationalId(4)));
    self::assertTrue($validator->isResident($generator->iqama(4)));
    self::assertFalse($validator->isValid($generator->wrongChecksum()));
  }

  /**
   * The shipped defaults are the documented ones.
   */
  public function testInstalledDefaults(): void {
    /** @var \Drupal\saudi_id_validator\ValidatorSettings $settings */
    $settings = $this->container->get(ValidatorSettings::class);

    self::assertTrue($settings->automaticValidationEnabled());
    self::assertSame(ValidatorSettings::DEFAULT_FIELD_NAMES, $settings->watchedFieldNames());
    self::assertTrue($settings->watches('national_id'));
    self::assertFalse($settings->watches('some_other_field'));
  }

  /**
   * Every type has a distinct leading digit and a translated label.
   */
  public function testTypeLabels(): void {
    foreach (IdType::cases() as $type) {
      self::assertNotSame('', (string) $type->label());
      self::assertSame($type, IdType::fromLeadingDigit($type->leadingDigit()));
    }

    self::assertNull(IdType::fromLeadingDigit('3'));
  }

}
