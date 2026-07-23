<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Event;

/**
 * Dispatched when an identification number was rejected.
 *
 * The metadata carries the reason, so a subscriber can tell a mistyped length
 * from a failed check digit — useful for rate-limiting or for spotting a form
 * whose users keep getting it wrong.
 *
 * @api
 *   Public and stable since 1.0.0.
 */
final class SaudiIdValidationFailedEvent extends SaudiIdValidationEventBase {

  /**
   * The event name.
   */
  public const EVENT_NAME = 'saudi_id_validator.validation_failed';

}
