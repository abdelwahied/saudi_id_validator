<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator;

/**
 * The immutable verdict on one identification number.
 *
 * Readonly on purpose: a result travels from the validator into events, forms
 * and other modules, and none of them may edit what the validator concluded.
 * Build one through the named constructors, never with `new`.
 *
 * @api
 *   Public and stable since 1.0.0. Readonly: construct it only through the
 *   named constructors, and only the validator does that.
 */
final readonly class IdMetadata {

  /**
   * Constructs an IdMetadata.
   *
   * @param bool $valid
   *   Whether the number passed every rule.
   * @param \Drupal\saudi_id_validator\IdType|null $type
   *   The detected type, or NULL when the number is invalid.
   * @param \Drupal\saudi_id_validator\FailureReason|null $reason
   *   Why it failed, or NULL when it did not.
   * @param string $normalised
   *   The digits as the validator read them, trimmed of surrounding space.
   */
  private function __construct(
    public bool $valid,
    public ?IdType $type,
    public ?FailureReason $reason,
    public string $normalised,
  ) {}

  /**
   * A passing result.
   *
   * @param \Drupal\saudi_id_validator\IdType $type
   *   The detected type.
   * @param string $normalised
   *   The ten digits.
   *
   * @return self
   *   The result.
   */
  public static function valid(IdType $type, string $normalised): self {
    return new self(TRUE, $type, NULL, $normalised);
  }

  /**
   * A failing result.
   *
   * @param \Drupal\saudi_id_validator\FailureReason $reason
   *   Why it failed.
   * @param string $normalised
   *   The value as read, kept so a caller can echo what was rejected.
   *
   * @return self
   *   The result.
   */
  public static function invalid(FailureReason $reason, string $normalised): self {
    return new self(FALSE, NULL, $reason, $normalised);
  }

  /**
   * The leading digit, when there is one.
   *
   * @return string|null
   *   The digit, or NULL when nothing usable was given.
   */
  public function firstDigit(): ?string {
    return $this->normalised === '' ? NULL : $this->normalised[0];
  }

  /**
   * A plain array of the verdict, for logging and for other modules.
   *
   * The label stays a TranslatableMarkup rather than a string: casting it here
   * would render it in whatever language happened to be active when the verdict
   * was reached, which is rarely the language it will be shown in. Cast it at
   * the point of display instead.
   *
   * @return array<string, mixed>
   *   The verdict: `valid`, `type`, `label`, `first_digit` and, when it failed,
   *   `reason`.
   */
  public function toArray(): array {
    return [
      'valid' => $this->valid,
      'type' => $this->type?->value,
      'label' => $this->type?->label(),
      'first_digit' => $this->firstDigit(),
      'reason' => $this->reason?->value,
    ];
  }

}
