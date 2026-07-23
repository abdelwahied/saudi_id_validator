<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\saudi_id_validator\ValidatorSettings;

/**
 * Configures where validation is applied and what the user is told.
 *
 * Note what is absent: nothing here relaxes a rule. The checksum and the
 * leading-digit test are properties of the number, not preferences, so making
 * them optional would only offer an administrator a way to let bad data in.
 *
 * @internal
 *   The form class may change shape at any time. What is stable is the route
 *   `saudi_id_validator.settings` and the configuration object it writes; read
 *   that through \Drupal\saudi_id_validator\ValidatorSettings.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'saudi_id_validator_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [ValidatorSettings::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(ValidatorSettings::CONFIG_NAME);

    $form['automatic_validation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate matching fields automatically'),
      '#default_value' => (bool) $config->get('automatic_validation'),
      '#description' => $this->t('Applies validation to any form field whose machine name appears below, without the form having to ask for it. Fields that attach the validator or the constraint themselves are always validated, whatever this setting says.'),
    ];

    $form['field_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field machine names'),
      '#default_value' => implode("\n", (array) $config->get('field_names')),
      '#description' => $this->t('One machine name per line.'),
      '#rows' => 6,
      '#states' => [
        'visible' => [
          ':input[name="automatic_validation"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['show_detected_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Tell the user which type was detected'),
      '#default_value' => (bool) $config->get('show_detected_type'),
      '#description' => $this->t('Shows "Saudi National ID" or "Resident ID (Iqama)" as a status message after a number validates.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(ValidatorSettings::CONFIG_NAME)
      ->set('automatic_validation', (bool) $form_state->getValue('automatic_validation'))
      ->set('field_names', $this->parseFieldNames((string) $form_state->getValue('field_names')))
      ->set('show_detected_type', (bool) $form_state->getValue('show_detected_type'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Turns the textarea into a clean list of machine names.
   *
   * @param string $raw
   *   The submitted text.
   *
   * @return array<int, string>
   *   Trimmed, de-duplicated, non-empty names.
   */
  private function parseFieldNames(string $raw): array {
    $names = preg_split('/\R/', $raw) ?: [];
    $names = array_map('trim', $names);
    $names = array_filter($names, static fn (string $name): bool => $name !== '');

    return array_values(array_unique($names));
  }

}
