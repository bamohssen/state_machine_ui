<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\ListBuilder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Lists Workflow config entities.
 */
class WorkflowListBuilder extends ConfigEntityListBuilder {

  public function buildHeader(): array {
    return [
      'label' => $this->t('Workflow'),
      'id' => $this->t('Machine name'),
      'group' => $this->t('Group'),
    ] + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array {
    return [
      'label' => $entity->label(),
      'id' => $entity->id(),
      'group' => $entity->get('group') ?: $this->t('None'),
    ] + parent::buildRow($entity);
  }

}
