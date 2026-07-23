<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\saudi_id_validator\IdMetadata;

/**
 * What every validation event carries.
 *
 * Subscribers get the value exactly as it arrived alongside the verdict, so a
 * listener that logs or audits can report what was typed rather than the
 * trimmed form the validator worked on.
 *
 * @api
 *   Public and stable since 1.0.0. Subscribe to the concrete events; this base
 *   exists so both carry the same two properties.
 */
abstract class SaudiIdValidationEventBase extends Event {

  /**
   * Constructs the event.
   *
   * @param string $originalValue
   *   The value as it was submitted, untrimmed.
   * @param \Drupal\saudi_id_validator\IdMetadata $metadata
   *   The verdict.
   */
  public function __construct(
    public readonly string $originalValue,
    public readonly IdMetadata $metadata,
  ) {}

}
