<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\state_machine_ui\Constant\FilterLogic;

/**
 * Checks whether a given transition is currently allowed for an entity.
 *
 * @api
 */
interface TransitionCheckerInterface {

  /**
   * Checks whether a transition to a given target state is currently allowed.
   *
   * A transition is allowed when at least one available transition (after
   * State Machine guard evaluation) leads to the target state.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to inspect.
   * @param string $field_name
   *   The machine name of the state field.
   * @param string $target_state_id
   *   The machine name of the desired target state (e.g. 'published').
   *
   * @return bool
   *   TRUE if at least one available transition leads to $target_state_id.
   */
  public function canTransitionTo(
    FieldableEntityInterface $entity,
    string $field_name,
    string $target_state_id,
  ): bool;

  /**
   * Checks transition allowance combined with target state metadata filters.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to inspect.
   * @param string $field_name
   *   The machine name of the state field.
   * @param string $target_state_id
   *   The machine name of the desired target state.
   * @param array<string, string[]> $metadata_filters
   *   Metadata constraints: key => list of required values.
   * @param \Drupal\state_machine_ui\Constant\FilterLogic $logic
   *   Inter-key logic: AND (all keys must pass) or OR (any key passes).
   *
   * @return bool
   *   TRUE when the transition is allowed AND the target state matches the
   *   metadata filters.
   */
  public function canTransitionToWithMetadata(
    FieldableEntityInterface $entity,
    string $field_name,
    string $target_state_id,
    array $metadata_filters,
    FilterLogic $logic = FilterLogic::And,
  ): bool;

}
