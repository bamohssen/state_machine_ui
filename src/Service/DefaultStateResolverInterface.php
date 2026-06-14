<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;

/**
 * Resolves the default (initial) state for a given workflow.
 *
 * Single responsibility: given a workflow, return the machine name of the
 * state that should be used as the initial value when no state is set yet.
 *
 * @api
 */
interface DefaultStateResolverInterface {

  /**
   * Gets the default state ID for the given workflow.
   *
   * Resolution order:
   *   1. The `default_state` key declared on the workflow config entity.
   *   2. The state with the lowest `weight` among this workflow's states.
   *   3. NULL when the workflow has no states at all.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow plugin instance.
   *
   * @return string|null
   *   The default state machine name, or NULL if none can be determined.
   */
  public function getDefault(WorkflowInterface $workflow): ?string;

}
