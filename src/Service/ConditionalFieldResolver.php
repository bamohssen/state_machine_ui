<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine_ui\Constant\Visibility;

/**
 * Resolves conditional rules into Drupal #states arrays.
 *
 * @internal
 */
final readonly class ConditionalFieldResolver implements ConditionalFieldResolverInterface {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getStates(array $conditions, string $field_name, string $state_field_selector): array {
    $show_values = [];
    $hide_values = [];

    foreach ($conditions as $rule) {
      if (!is_array($rule) || ($rule['field'] ?? '') !== $field_name || empty($rule['state'])) {
        continue;
      }
      $visibility = $this->normalizeVisibility($rule['visibility'] ?? NULL);
      if ($visibility === Visibility::Hide) {
        $hide_values[] = ['value' => (string) $rule['state']];
      }
      else {
        $show_values[] = ['value' => (string) $rule['state']];
      }
    }

    // Show wins: if any show rule exists the field behaves as a whitelist and
    // hide rules are ignored.
    if (!empty($show_values)) {
      return [
        '#states' => [
          'visible' => array_map(
            static fn(array $option_value): array => [$state_field_selector => $option_value],
            $show_values,
          ),
        ],
      ];
    }

    if (!empty($hide_values)) {
      return [
        '#states' => [
          'invisible' => array_map(
            static fn(array $option_value): array => [$state_field_selector => $option_value],
            $hide_values,
          ),
        ],
      ];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getRequiredFields(array $conditions, string $target_state): array {
    $required_fields = [];

    foreach ($conditions as $rule) {
      if (!is_array($rule) || empty($rule['field']) || empty($rule['required'])) {
        continue;
      }
      if (($rule['state'] ?? '') !== $target_state) {
        continue;
      }
      $field_name = (string) $rule['field'];
      if ($this->isFieldRequiredForState($conditions, $field_name, $target_state)) {
        $required_fields[$field_name] = $field_name;
      }
    }

    return array_values($required_fields);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function isFieldRequiredForState(array $conditions, string $field_name, string $target_state): bool {
    $has_show_rule       = FALSE;
    $shown_for_target    = FALSE;
    $required_for_target = FALSE;
    $hidden_for_target   = FALSE;

    foreach ($conditions as $rule) {
      if (!is_array($rule) || ($rule['field'] ?? '') !== $field_name) {
        continue;
      }
      $is_show   = $this->normalizeVisibility($rule['visibility'] ?? NULL) === Visibility::Show;
      $is_target = (string) ($rule['state'] ?? '') === $target_state;

      if ($is_show) {
        $has_show_rule = TRUE;
        if ($is_target) {
          $shown_for_target = TRUE;
          if (!empty($rule['required'])) {
            $required_for_target = TRUE;
          }
        }
      }
      elseif ($is_target) {
        $hidden_for_target = TRUE;
      }
    }

    if (!$required_for_target) {
      return FALSE;
    }
    // Show wins: required only applies when the field is explicitly visible.
    return $has_show_rule ? $shown_for_target : !$hidden_for_target;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getReferencedFields(array $conditions): array {
    $field_names = [];

    foreach ($conditions as $rule) {
      if (is_array($rule) && !empty($rule['field'])) {
        $field_names[(string) $rule['field']] = (string) $rule['field'];
      }
    }

    return array_values($field_names);
  }

  /**
   * Normalizes a raw visibility value to a Visibility enum case.
   *
   * @param mixed $value
   *   The raw visibility value from a condition rule.
   *
   * @return \Drupal\state_machine_ui\Constant\Visibility
   *   The matching case, defaulting to Visibility::Show for unknown values.
   */
  private function normalizeVisibility(mixed $value): Visibility {
    return Visibility::tryFrom((string) $value) ?? Visibility::Show;
  }

}
