<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\saudi_id_validator\IdType;
use Drupal\saudi_id_validator\SaudiIdValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SaudiId constraint through the shared validator service.
 *
 * Holds no rules of its own: it translates between the typed-data world and the
 * validator, so a field constraint and a form field can never disagree about
 * what a valid number is.
 *
 * @internal
 *   An adapter, not the API. The public surface is the `SaudiId` constraint
 *   itself — attach it with addConstraint('SaudiId'); never reference this
 *   class directly.
 */
final class SaudiIdConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a SaudiIdConstraintValidator.
   *
   * @param \Drupal\saudi_id_validator\SaudiIdValidatorInterface $validator
   *   The validator.
   */
  public function __construct(private readonly SaudiIdValidatorInterface $validator) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get(SaudiIdValidatorInterface::class));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof SaudiIdConstraint) {
      return;
    }

    $id = $this->extract($value);

    // An absent value is the business of a NotNull constraint, not this one: a
    // field may legitimately be both optional and, when filled, a valid number.
    if ($id === NULL || trim($id) === '') {
      return;
    }

    $metadata = $this->validator->getMetadata($id);

    if (!$metadata->valid) {
      $this->context->addViolation((string) $metadata->reason->message());

      return;
    }

    $expected = $constraint->requireType === NULL ? NULL : IdType::tryFrom($constraint->requireType);

    if ($expected !== NULL && $metadata->type !== $expected) {
      $this->context->addViolation($constraint->wrongTypeMessage, [
        '@expected' => (string) $expected->label(),
        '@actual' => (string) $metadata->type->label(),
      ]);
    }
  }

  /**
   * The string behind a typed-data item, field item list or plain value.
   *
   * @param mixed $value
   *   Whatever the validator was handed.
   *
   * @return string|null
   *   The value as a string, or NULL when there is nothing to check.
   */
  private function extract(mixed $value): ?string {
    if ($value === NULL) {
      return NULL;
    }

    if (is_scalar($value)) {
      return (string) $value;
    }

    // A field item list arrives when the constraint sits on the field rather
    // than on its property. Read the item's declared main property instead of
    // assuming `value`, so the constraint also fits a field type that names it
    // something else.
    if ($value instanceof FieldItemListInterface) {
      if ($value->isEmpty()) {
        return NULL;
      }

      $property = $value->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
      $item = $value->first()->getValue();

      return isset($item[$property]) && is_scalar($item[$property]) ? (string) $item[$property] : NULL;
    }

    if ($value instanceof TypedDataInterface) {
      $inner = $value->getValue();

      return is_scalar($inner) ? (string) $inner : NULL;
    }

    return NULL;
  }

}
