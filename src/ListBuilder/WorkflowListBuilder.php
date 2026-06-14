<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\ListBuilder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Lists Workflow config entities.
 */
final class WorkflowListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildHeader(): array {
    return [
      'label' => $this->t('Workflow'),
      'id' => $this->t('Machine name'),
      'group' => $this->t('Group'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildRow(EntityInterface $entity): array {
    return [
      'label' => $entity->label(),
      'id' => $entity->id(),
      'group' => $entity->get('group') ?: $this->t('None'),
    ] + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Adds a "Transitions" operation between Edit and Delete, deep-linking to
   * the workflow-scoped transition collection.
   */
  #[\Override]
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    $operations['transitions'] = [
      'title' => $this->t('Transitions'),
      'weight' => 20,
      'url' => Url::fromRoute(
        'entity.workflow_transition.collection',
        ['workflow_state_machine' => $entity->id()],
      ),
    ];
    return $operations;
  }

}
