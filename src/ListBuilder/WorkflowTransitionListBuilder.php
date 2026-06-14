<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\ListBuilder;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Entity\WorkflowTransition;

/**
 * Lists the transitions of a single workflow as a draggable table.
 *
 * The weight column drives the order in which State Machine evaluates
 * transitions at runtime.
 */
final class WorkflowTransitionListBuilder extends DraggableListBuilder {

  /**
   * The parent workflow this listing is filtered by.
   */
  private ?WorkflowStateMachine $workflowContext = NULL;

  /**
   * Renders the listing for a specific workflow.
   *
   * Callers must use this method rather than {@see ::render()} so the
   * listing is scoped; an unscoped render() returns an empty list by
   * design.
   *
   * @param \Drupal\state_machine_ui\Entity\WorkflowStateMachine $workflow
   *   The parent workflow whose transitions should be listed.
   *
   * @return array
   *   The render array.
   */
  public function renderForWorkflow(WorkflowStateMachine $workflow): array {
    $this->workflowContext = $workflow;
    return $this->render();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function getEntityIds(): array {
    if ($this->workflowContext === NULL) {
      return [];
    }
    $query = $this->getStorage()->getQuery()
      ->condition('workflow', $this->workflowContext->id())
      ->sort($this->entityType->getKey('weight'))
      ->sort($this->entityType->getKey('id'));
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getFormId(): string {
    return 'state_machine_ui_transitions_overview';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildHeader(): array {
    return [
      'label' => $this->t('Transition'),
      'key' => $this->t('Key'),
      'from' => $this->t('From'),
      'to' => $this->t('To'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof WorkflowTransition);
    $row['label'] = $entity->label();
    $row['key'] = ['#markup' => $entity->getKey()];
    $row['from'] = ['#markup' => implode(', ', $entity->getFromStates())];
    $row['to'] = ['#markup' => $entity->getToState()];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getDefaultOperations(EntityInterface $entity): array {
    assert($entity instanceof WorkflowTransition);
    $route_params = [
      'workflow_state_machine' => $entity->getWorkflowId(),
      'workflow_transition' => $entity->id(),
    ];

    return [
      'edit' => [
        'title' => $this->t('Edit'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.workflow_transition.edit_form', $route_params),
      ],
      'delete' => [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => Url::fromRoute('entity.workflow_transition.delete_form', $route_params),
      ],
    ];
  }

}
