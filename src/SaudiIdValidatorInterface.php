<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator;

/**
 * Validates and classifies Saudi identification numbers.
 *
 * The single place any part of a site may decide whether an identification
 * number is well formed: the Form API validator, the entity constraint and the
 * automatic field validation all answer through this contract, so a rule
 * changes in one implementation rather than in every caller.
 *
 * Every method is offline and side-effect free — no registry is contacted, and
 * a well-formed number is not a claim that the person exists.
 *
 * @api
 *   Public and stable since 1.0.0. Inject this, never the implementation.
 */
interface SaudiIdValidatorInterface {

  /**
   * Whether the value is a well-formed identification number.
   *
   * @param string $id
   *   The candidate, exactly as the user typed it.
   *
   * @return bool
   *   TRUE when the format, the leading digit and the checksum all hold.
   */
  public function isValid(string $id): bool;

  /**
   * The type of the number, when it is valid.
   *
   * @param string $id
   *   The candidate.
   *
   * @return \Drupal\saudi_id_validator\IdType|null
   *   The type, or NULL when the number is not valid at all.
   */
  public function detectType(string $id): ?IdType;

  /**
   * Whether the number belongs to a Saudi citizen.
   *
   * @param string $id
   *   The candidate.
   *
   * @return bool
   *   TRUE for a valid national identity number.
   */
  public function isSaudiCitizen(string $id): bool;

  /**
   * Whether the number belongs to a resident.
   *
   * @param string $id
   *   The candidate.
   *
   * @return bool
   *   TRUE for a valid Iqama number.
   */
  public function isResident(string $id): bool;

  /**
   * Everything known about the number, in one immutable value.
   *
   * @param string $id
   *   The candidate.
   *
   * @return \Drupal\saudi_id_validator\IdMetadata
   *   The result, including why an invalid number failed.
   */
  public function getMetadata(string $id): IdMetadata;

}
