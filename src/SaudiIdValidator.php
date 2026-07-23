<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator;

use Drupal\saudi_id_validator\Checksum\LuhnChecksum;
use Drupal\saudi_id_validator\Event\SaudiIdValidatedEvent;
use Drupal\saudi_id_validator\Event\SaudiIdValidationFailedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The one implementation of the identification-number rules.
 *
 * Three questions in order, cheapest first: is it ten ASCII digits, does it
 * start with a digit that denotes a type, and does the check digit add up. The
 * order matters for the message the user gets — being told the checksum failed
 * when eleven digits were typed would send them looking in the wrong place.
 *
 * Only ASCII digits are accepted. Arabic-Indic digits, non-breaking spaces and
 * zero-width characters all fail the format test rather than being folded into
 * ASCII: a number that looks right but carries invisible characters is exactly
 * the sort of value that should not reach storage.
 *
 * @internal
 *   The implementation, not the API. Type-hint and inject
 *   \Drupal\saudi_id_validator\SaudiIdValidatorInterface instead — that is what
 *   the container binds, and what a site may rebind to something else.
 */
final class SaudiIdValidator implements SaudiIdValidatorInterface {

  /**
   * Constructs a SaudiIdValidator.
   *
   * @param \Drupal\saudi_id_validator\Checksum\LuhnChecksum $checksum
   *   The check-digit arithmetic.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The dispatcher other modules subscribe to.
   */
  public function __construct(
    private readonly LuhnChecksum $checksum,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isValid(string $id): bool {
    return $this->getMetadata($id)->valid;
  }

  /**
   * {@inheritdoc}
   */
  public function detectType(string $id): ?IdType {
    return $this->getMetadata($id)->type;
  }

  /**
   * {@inheritdoc}
   */
  public function isSaudiCitizen(string $id): bool {
    return $this->detectType($id) === IdType::SaudiNationalId;
  }

  /**
   * {@inheritdoc}
   */
  public function isResident(string $id): bool {
    return $this->detectType($id) === IdType::Iqama;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(string $id): IdMetadata {
    $metadata = $this->decide($id);

    $this->eventDispatcher->dispatch(
      $metadata->valid
        ? new SaudiIdValidatedEvent($id, $metadata)
        : new SaudiIdValidationFailedEvent($id, $metadata),
    );

    return $metadata;
  }

  /**
   * Applies the rules, without telling anybody about the outcome.
   *
   * Separate from getMetadata() so the event is dispatched exactly once per
   * public call, however many rules were consulted along the way.
   *
   * @param string $id
   *   The candidate.
   *
   * @return \Drupal\saudi_id_validator\IdMetadata
   *   The verdict.
   */
  private function decide(string $id): IdMetadata {
    // Surrounding whitespace is a copy-and-paste artefact rather than part of
    // what the user meant to type, so it is trimmed. Anything inside the number
    // is not: an embedded space means the value is wrong, not untidy.
    $candidate = trim($id);

    if (preg_match('/^\d{' . LuhnChecksum::LENGTH . '}$/', $candidate) !== 1) {
      return IdMetadata::invalid(FailureReason::NotTenDigits, $candidate);
    }

    $type = IdType::fromLeadingDigit($candidate[0]);

    if ($type === NULL) {
      return IdMetadata::invalid(FailureReason::UnknownLeadingDigit, $candidate);
    }

    if (!$this->checksum->isValid($candidate)) {
      return IdMetadata::invalid(FailureReason::ChecksumFailed, $candidate);
    }

    return IdMetadata::valid($type, $candidate);
  }

}
