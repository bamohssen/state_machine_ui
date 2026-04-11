<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Converts conditional rules into Drupal #states arrays and required field lists.
 */
interface ConditionalFieldResolverInterface {

  /**
   * Resolves the #states render array for a given target field.
   *
   * @param array $conditions
   *   The conditions from widget settings.
   * @param string $field_name
   *   The target field machine name.
   * @param string $state_field_selector
   *   The CSS selector for the state widget select element.
   *
   * @return array
   *   A Drupal #states array, or empty if no rules apply.
   */
  public function resolveStates(array $conditions, string $field_name, string $state_field_selector): array;

  /**
   * Gets fields that are conditionally required for a given target state.
   *
   * @param array $conditions
   *   The conditions from widget settings.
   * @param string $target_state
   *   The selected target state ID.
   *
   * @return string[]
   *   Field names that are required for this target state.
   */
  public function getRequiredFields(array $conditions, string $target_state): array;

  /**
   * Gets all field names referenced in the conditions.
   *
   * @param array $conditions
   *   The conditions from widget settings.
   *
   * @return string[]
   *   Unique field names.
   */
  public function getReferencedFields(array $conditions): array;

}
