<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\ListBuilder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Lists Workflow Group config entities.
 */
final class WorkflowGroupListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildHeader(): array {
    return [
      'label' => $this->t('Group'),
      'id' => $this->t('Machine name'),
      'entity_type' => $this->t('Entity type'),
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
      'entity_type' => $entity->get('entity_type') ?: $this->t('Not set'),
    ] + parent::buildRow($entity);
  }

}
