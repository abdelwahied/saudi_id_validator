<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Event;

/**
 * Dispatched when an identification number passed every rule.
 *
 * A subscriber may read the value and the detected type — it cannot change the
 * verdict, which is already decided by the time this is sent.
 *
 * @api
 *   Public and stable since 1.0.0.
 */
final class SaudiIdValidatedEvent extends SaudiIdValidationEventBase {

  /**
   * The event name.
   */
  public const EVENT_NAME = 'saudi_id_validator.validated';

}
