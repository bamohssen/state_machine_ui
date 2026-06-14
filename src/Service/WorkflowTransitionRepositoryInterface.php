<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine_ui\Entity\WorkflowTransition;

/**
 * Read-side facade for WorkflowTransition config entities.
 *
 * Prefer this service over direct $storage->loadByProperties() calls so
 * query, ordering and per-request memoization stay consistent.
 */
interface WorkflowTransitionRepositoryInterface {

  /**
   * Returns all transitions of a given workflow, ordered by weight.
   *
   * @param string $workflow_id
   *   The parent workflow ID.
   *
   * @return \Drupal\state_machine_ui\Entity\WorkflowTransition[]
   *   Transitions indexed by full entity ID.
   */
  public function loadByWorkflow(string $workflow_id): array;

  /**
   * Returns transitions of a workflow that reference any of the given states.
   *
   * Used to detect orphaned references before a state is removed from a
   * workflow.
   *
   * @param string $workflow_id
   *   The parent workflow ID.
   * @param string[] $state_keys
   *   State keys to look for in the from/to columns.
   *
   * @return \Drupal\state_machine_ui\Entity\WorkflowTransition[]
   *   Matching transitions indexed by full entity ID.
   */
  public function findReferencingStates(string $workflow_id, array $state_keys): array;

  /**
   * Loads a single transition by its (workflow, key) tuple.
   *
   * @param string $workflow_id
   *   The parent workflow ID.
   * @param string $key
   *   The transition machine name (unprefixed).
   *
   * @return \Drupal\state_machine_ui\Entity\WorkflowTransition|null
   *   The transition, or NULL when not found.
   */
  public function loadByKey(string $workflow_id, string $key): ?WorkflowTransition;

}
