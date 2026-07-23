<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Validator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\saudi_id_validator\SaudiIdValidatorInterface;
use Drupal\saudi_id_validator\ValidatorSettings;

/**
 * The Form API entry point: `#element_validate` for an identification field.
 *
 * Attach it to any textfield:
 * @code
 * $form['national_id'] = [
 *   '#type' => 'textfield',
 *   '#title' => $this->t('National ID'),
 *   '#element_validate' => [
 *     [SaudiIdElementValidator::class, 'validate'],
 *   ],
 * ];
 * @endcode
 *
 * The callback is static because the Form API stores it in the form array and
 * calls it without an object; it holds no logic of its own and immediately
 * hands the value to the validator service, which is where every rule lives.
 *
 * @api
 *   Public and stable since 1.0.0. The ::validate callback signature is the
 *   contract.
 */
final class SaudiIdElementValidator {

  /**
   * Validates one element's value.
   *
   * An empty value is left alone. Whether the field is mandatory is the form's
   * decision, expressed with '#required', and answering it here as well would
   * make an optional field impossible.
   *
   * @param array $element
   *   The form element. Passed by reference by the Form API, so the type stays
   *   the generic array the caller provides.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validate(array &$element, FormStateInterface $form_state): void {
    $value = (string) ($element['#value'] ?? '');

    if (trim($value) === '') {
      return;
    }

    $metadata = self::validator()->getMetadata($value);

    if (!$metadata->valid) {
      $form_state->setError($element, $metadata->reason->message());

      return;
    }

    if (self::settings()->showDetectedType()) {
      self::messenger()->addStatus($metadata->type->label());
    }
  }

  /**
   * The validator service.
   *
   * @return \Drupal\saudi_id_validator\SaudiIdValidatorInterface
   *   The validator.
   */
  private static function validator(): SaudiIdValidatorInterface {
    return \Drupal::service(SaudiIdValidatorInterface::class);
  }

  /**
   * The module settings.
   *
   * @return \Drupal\saudi_id_validator\ValidatorSettings
   *   The settings.
   */
  private static function settings(): ValidatorSettings {
    return \Drupal::service(ValidatorSettings::class);
  }

  /**
   * The messenger.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface
   *   The messenger.
   */
  private static function messenger(): MessengerInterface {
    return \Drupal::messenger();
  }

}
