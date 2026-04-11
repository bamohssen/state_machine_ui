<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Resolves conditional rules into Drupal #states arrays.
 */
final class ConditionalFieldResolver implements ConditionalFieldResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolveStates(array $conditions, string $field_name, string $state_field_selector): array {
    $visible_values = [];
    foreach ($conditions as $rule) {
      if (!is_array($rule)) {
        continue;
      }
      if (($rule['field'] ?? '') === $field_name && !empty($rule['state'])) {
        $visible_values[] = ['value' => $rule['state']];
      }
    }
    if (empty($visible_values)) {
      return [];
    }
    return [
      '#states' => [
        'visible' => array_map(
          static fn(array $val): array => [$state_field_selector => $val],
          $visible_values,
        ),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredFields(array $conditions, string $target_state): array {
    $required = [];
    foreach ($conditions as $rule) {
      if (!is_array($rule)) {
        continue;
      }
      if (($rule['state'] ?? '') === $target_state && !empty($rule['required']) && !empty($rule['field'])) {
        $required[] = $rule['field'];
      }
    }
    return array_unique($required);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedFields(array $conditions): array {
    $fields = [];
    foreach ($conditions as $rule) {
      if (!is_array($rule)) {
        continue;
      }
      if (!empty($rule['field'])) {
        $fields[] = $rule['field'];
      }
    }
    return array_unique($fields);
  }

}
