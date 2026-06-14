<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\ListBuilder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Lists Workflow Metadata Schema config entities.
 */
final class MetadataSchemaListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildHeader(): array {
    return [
      'label' => $this->t('Schema name'),
      'id' => $this->t('Machine name'),
      'fields_count' => $this->t('Fields count'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildRow(EntityInterface $entity): array {
    $field_definitions = $entity->get('field_definitions') ?? [];
    return [
      'label' => $entity->label(),
      'id' => $entity->id(),
      'fields_count' => is_array($field_definitions) ? count($field_definitions) : 0,
    ] + parent::buildRow($entity);
  }

}
