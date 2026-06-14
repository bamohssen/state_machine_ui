<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Session\AccountInterface;

/**
 * Filters a list of state_machine transitions by per-transition permission.
 *
 * The permission ID format is given by
 * {@see \Drupal\state_machine_ui\Constant\StateMachineUiConstants::PERM_TRANSITION_FORMAT}
 * — one permission per workflow × transition pair.
 */
interface TransitionAccessCheckerInterface {

  /**
   * Returns transitions the given account is permitted to fire.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   * @param array<int|string, \Drupal\state_machine\Plugin\Workflow\WorkflowTransition> $transitions
   *   Candidate transitions.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account whose permissions are checked.
   *
   * @return array<int|string, \Drupal\state_machine\Plugin\Workflow\WorkflowTransition>
   *   The filtered transitions, preserving the input keys.
   */
  public function filter(string $workflow_id, array $transitions, AccountInterface $account): array;

  /**
   * Returns the canonical permission ID for one workflow/transition pair.
   */
  public function permissionId(string $workflow_id, string $transition_key): string;

}
