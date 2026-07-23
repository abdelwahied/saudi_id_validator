<?php

declare(strict_types=1);

namespace Drupal\Tests\saudi_id_validator\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\saudi_id_validator\Checksum\LuhnChecksum;
use Drupal\saudi_id_validator\IdType;
use Drupal\Tests\saudi_id_validator\Support\SaudiIdGenerator;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the entity constraint, the path JSON:API and REST both travel.
 *
 * Validating the entity rather than a form proves the rule holds for every way
 * in — a saved entity, an API request, a migration — because they all run the
 * same typed-data constraints.
 */
#[RunTestsInSeparateProcesses]
#[Group('saudi_id_validator')]
final class SaudiIdConstraintTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'entity_test',
    'user',
    'system',
    'saudi_id_validator',
  ];

  /**
   * The generator producing the test values.
   */
  private SaudiIdGenerator $generator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['saudi_id_validator']);

    $this->generator = new SaudiIdGenerator($this->container->get(LuhnChecksum::class));

    FieldStorageConfig::create([
      'field_name' => 'field_national_id',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_national_id',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'National ID',
    ])->save();
  }

  /**
   * A valid number of either type passes the constraint.
   */
  public function testValidNumbersPass(): void {
    foreach ([$this->generator->nationalId(1), $this->generator->iqama(1)] as $id) {
      $violations = $this->violationsFor($id);

      self::assertCount(0, $violations, sprintf('%s should pass, got: %s', $id, $this->describe($violations)));
    }
  }

  /**
   * A failing check digit is reported as a violation, not silently accepted.
   */
  public function testWrongChecksumIsReported(): void {
    $violations = $this->violationsFor($this->generator->wrongChecksum());

    self::assertCount(1, $violations);
    self::assertStringContainsString('Checksum', (string) $violations->get(0)->getMessage());
  }

  /**
   * A wrong length is reported with the length message.
   */
  public function testWrongLengthIsReported(): void {
    $violations = $this->violationsFor($this->generator->wrongLength(9));

    self::assertCount(1, $violations);
    self::assertStringContainsString('10 digits', (string) $violations->get(0)->getMessage());
  }

  /**
   * An empty value is left to whatever requires the field, if anything does.
   */
  public function testEmptyValueIsNotThisConstraintsBusiness(): void {
    self::assertCount(0, $this->violationsFor(''));
    self::assertCount(0, $this->violationsFor(NULL));
  }

  /**
   * A field restricted to citizens refuses a valid Iqama.
   */
  public function testTypeRestriction(): void {
    $entity = EntityTest::create();
    $definition = $entity->get('field_national_id')->getFieldDefinition();
    $definition->addConstraint('SaudiId', ['requireType' => IdType::SaudiNationalId->value]);

    $entity->set('field_national_id', $this->generator->iqama());
    $violations = $entity->get('field_national_id')->validate();

    self::assertCount(1, $violations);
    self::assertStringContainsString('Saudi National ID', (string) $violations->get(0)->getMessage());

    $entity->set('field_national_id', $this->generator->nationalId());

    self::assertCount(0, $entity->get('field_national_id')->validate());
  }

  /**
   * Validates one value through the constraint on the test field.
   *
   * @param string|null $id
   *   The value to store.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The violations raised.
   */
  private function violationsFor(?string $id): object {
    $entity = EntityTest::create();
    $entity->get('field_national_id')->getFieldDefinition()->addConstraint('SaudiId');
    $entity->set('field_national_id', $id);

    return $entity->get('field_national_id')->validate();
  }

  /**
   * The violation messages, for a readable failure.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violations.
   *
   * @return string
   *   The messages, comma separated.
   */
  private function describe(object $violations): string {
    $messages = [];

    foreach ($violations as $violation) {
      $messages[] = (string) $violation->getMessage();
    }

    return implode(', ', $messages);
  }

}
