<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Converts conditional rules into Drupal #states arrays and required field lists.
 *
 * Note: "show" rules take precedence — when any show rule targets a field,
 * all hide rules for that field are ignored (whitelist semantics).
 *
 * @api
 */
interface ConditionalFieldResolverInterface {

  /**
   * Gets the #states render array for a given target field.
   *
   * Precedence when a field has mixed show/hide rules: show wins. The field
   * is treated as a whitelist (visible only in declared show states). Any
   * hide rules on that field are ignored in that case.
   *
   * @param array $conditions
   *   The conditions array from widget settings.
   * @param string $field_name
   *   The target field name.
   * @param string $state_field_selector
   *   The jQuery selector for the state field input.
   *
   * @return array
   *   A render array containing a '#states' key, or an empty array.
   */
  public function getStates(array $conditions, string $field_name, string $state_field_selector): array;

  /**
   * Gets fields that are conditionally required for a given target state.
   *
   * A field is only included if it is effectively visible for that state
   * (see isFieldRequiredForState()).
   *
   * @param array $conditions
   *   The conditions array from widget settings.
   * @param string $target_state
   *   The target workflow state machine name.
   *
   * @return string[]
   *   Field names that are required and visible for the given target state.
   */
  public function getRequiredFields(array $conditions, string $target_state): array;

  /**
   * Determines whether a field is required AND visible for the given state.
   *
   * Takes show/hide precedence rules into account: if any show rule exists for
   * the field, the field is only visible (and therefore only required) in
   * states that have an explicit show rule.
   *
   * @param array $conditions
   *   The conditions array from widget settings.
   * @param string $field_name
   *   The field name to check.
   * @param string $target_state
   *   The target workflow state machine name.
   *
   * @return bool
   *   TRUE if the field is both required and effectively visible for the state.
   */
  public function isFieldRequiredForState(array $conditions, string $field_name, string $target_state): bool;

  /**
   * Gets all field names referenced in the conditions.
   *
   * @param array $conditions
   *   The conditions array from widget settings.
   *
   * @return string[]
   *   Unique field names referenced across all condition rules.
   */
  public function getReferencedFields(array $conditions): array;

}
