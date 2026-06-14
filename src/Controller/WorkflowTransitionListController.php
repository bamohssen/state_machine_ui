<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\ListBuilder\WorkflowTransitionListBuilder;

/**
 * Renders the transitions collection page scoped to a single workflow.
 *
 * The default _entity_list handler produces an unscoped, cross-workflow
 * listing. This controller delegates to the list builder with the parent
 * workflow as a filter, keeping the user inside the workflow's context.
 */
final class WorkflowTransitionListController extends ControllerBase {

  /**
   * Route handler — renders the scoped transition listing.
   *
   * @param \Drupal\state_machine_ui\Entity\WorkflowStateMachine $workflow_state_machine
   *   Upcasted from the URL parameter of the same name.
   *
   * @return array
   *   The render array for the listing page.
   */
  public function list(WorkflowStateMachine $workflow_state_machine): array {
    $list_builder = $this->entityTypeManager()->getListBuilder('workflow_transition');
    assert($list_builder instanceof WorkflowTransitionListBuilder);
    return $list_builder->renderForWorkflow($workflow_state_machine);
  }

  /**
   * Title callback — surfaces the parent workflow label in the page title.
   *
   * @param \Drupal\state_machine_ui\Entity\WorkflowStateMachine $workflow_state_machine
   *   Upcasted from the URL parameter of the same name.
   */
  public function title(WorkflowStateMachine $workflow_state_machine): string {
    return (string) $this->t('Transitions of @workflow', ['@workflow' => $workflow_state_machine->label()]);
  }

}
