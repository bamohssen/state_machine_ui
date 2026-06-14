<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;

/**
 * Filters transitions by Drupal permission, one permission per transition.
 *
 * Stateless and side-effect-free; suitable for use in widget render paths.
 *
 * @internal
 */
final class TransitionAccessChecker implements TransitionAccessCheckerInterface {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function filter(string $workflow_id, array $transitions, AccountInterface $account): array {
    return array_filter(
      $transitions,
      fn(WorkflowTransition $transition): bool => $account->hasPermission(
        $this->permissionId($workflow_id, $transition->getId()),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function permissionId(string $workflow_id, string $transition_key): string {
    return sprintf(StateMachineUiConstants::PERM_TRANSITION_FORMAT, $workflow_id, $transition_key);
  }

}
