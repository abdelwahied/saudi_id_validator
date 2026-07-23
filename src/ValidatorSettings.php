<?php

declare(strict_types=1);

namespace Drupal\saudi_id_validator;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Typed, read-only access to saudi_id_validator.settings.
 *
 * Every consumer asks these questions rather than reading the config keys, so a
 * renamed key is a change here and nowhere else. Values are read through the
 * factory on each call, so a change on the settings form takes effect at once.
 *
 * There is deliberately no setting that relaxes a rule. What is configurable is
 * where validation is applied and what the user is told — never whether a
 * number that fails the rules is nonetheless accepted.
 *
 * @api
 *   Public and stable since 1.0.0, along with the configuration object it
 *   reads.
 */
final class ValidatorSettings {

  public const CONFIG_NAME = 'saudi_id_validator.settings';

  /**
   * The field machine names watched when nothing is configured.
   *
   * Shipped as the install default and used again here as the fallback, so a
   * site that empties the setting gets no automatic validation rather than a
   * surprise list.
   */
  public const DEFAULT_FIELD_NAMES = [
    'national_id',
    'saudi_id',
    'identity_number',
    'iqama',
    'id_number',
  ];

  /**
   * Constructs a ValidatorSettings.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Whether fields are validated automatically by machine name.
   *
   * @return bool
   *   TRUE when automatic validation is on.
   */
  public function automaticValidationEnabled(): bool {
    return (bool) $this->config()->get('automatic_validation');
  }

  /**
   * The field machine names that receive validation automatically.
   *
   * @return array<int, string>
   *   Trimmed, non-empty machine names.
   */
  public function watchedFieldNames(): array {
    $value = $this->config()->get('field_names');

    if (!is_array($value)) {
      return [];
    }

    $names = array_map('trim', array_map('strval', $value));

    return array_values(array_filter($names, static fn (string $name): bool => $name !== ''));
  }

  /**
   * Whether the given machine name is watched.
   *
   * @param string $name
   *   A form element or field machine name.
   *
   * @return bool
   *   TRUE when automatic validation applies to it.
   */
  public function watches(string $name): bool {
    return $this->automaticValidationEnabled() && in_array($name, $this->watchedFieldNames(), TRUE);
  }

  /**
   * Whether the detected type is shown back to the user.
   *
   * @return bool
   *   TRUE when a message naming the type is displayed.
   */
  public function showDetectedType(): bool {
    return (bool) $this->config()->get('show_detected_type');
  }

  /**
   * The immutable configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config.
   */
  private function config(): object {
    return $this->configFactory->get(self::CONFIG_NAME);
  }

}
