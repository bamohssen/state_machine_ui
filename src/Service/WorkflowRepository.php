<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_ui\Constant\FilterLogic;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Default implementation of WorkflowRepositoryInterface.
 *
 * @internal
 */
final readonly class WorkflowRepository implements WorkflowRepositoryInterface {

  /**
   * Constructs a WorkflowRepository instance.
   *
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The workflow plugin manager.
   * @param \Drupal\state_machine_ui\Service\MetadataFilterInterface $metadataFilter
   *   The metadata filter service.
   * @param \Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface $metadataReader
   *   The workflow metadata reader.
   */
  public function __construct(
    #[Autowire(service: 'plugin.manager.workflow')]
    private WorkflowManagerInterface $workflowManager,
    private MetadataFilterInterface $metadataFilter,
    private WorkflowMetadataReaderInterface $metadataReader,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(FieldableEntityInterface $entity, string $field_name): ?WorkflowInterface {
    $item = $this->getStateItem($entity, $field_name);
    return $item?->getWorkflow();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentStateId(FieldableEntityInterface $entity, string $field_name): ?string {
    $state_id = $this->getStateItem($entity, $field_name)?->getId();
    return ($state_id !== NULL && $state_id !== '') ? $state_id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableTransitions(FieldableEntityInterface $entity, string $field_name): array {
    $item = $this->getStateItem($entity, $field_name);
    return $item !== NULL ? $item->getTransitions() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function canTransitionTo(
    FieldableEntityInterface $entity,
    string $field_name,
    string $target_state_id,
  ): bool {
    foreach ($this->getAvailableTransitions($entity, $field_name) as $transition) {
      if ($transition->getToState()->getId() === $target_state_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function canTransitionToWithMetadata(
    FieldableEntityInterface $entity,
    string $field_name,
    string $target_state_id,
    array $metadata_filters,
    FilterLogic $logic = FilterLogic::And,
  ): bool {
    if (!$this->canTransitionTo($entity, $field_name, $target_state_id)) {
      return FALSE;
    }

    if (empty($metadata_filters)) {
      return TRUE;
    }

    $workflow = $this->getWorkflow($entity, $field_name);
    if ($workflow === NULL) {
      return FALSE;
    }

    $allowed = $this->metadataFilter->filterStates(
      $workflow->getPluginId(),
      [$target_state_id],
      $metadata_filters,
      $logic,
    );

    return in_array($target_state_id, $allowed, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatesByMetadata(
    string $workflow_id,
    string $metadata_key,
    string $metadata_value,
  ): array {
    $all_state_ids = $this->getAllStateIds($workflow_id);
    if ($all_state_ids === []) {
      return [];
    }

    return $this->metadataFilter->filterStates(
      $workflow_id,
      $all_state_ids,
      [$metadata_key => [$metadata_value]],
      FilterLogic::And,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionsByMetadata(
    string $workflow_id,
    string $metadata_key,
    string $metadata_value,
  ): array {
    $all_transition_ids = $this->getAllTransitionIds($workflow_id);
    if ($all_transition_ids === []) {
      return [];
    }

    return $this->metadataFilter->filterTransitions(
      $workflow_id,
      $all_transition_ids,
      [$metadata_key => [$metadata_value]],
      FilterLogic::And,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getStateMetadata(string $workflow_id, string $state_id): array {
    return $this->metadataReader->getStateValues($workflow_id, $state_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionMetadata(string $workflow_id, string $transition_id): array {
    return $this->metadataReader->getTransitionValues($workflow_id, $transition_id);
  }

  /**
   * Returns the StateItemInterface for a given entity field, or NULL.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to inspect.
   * @param string $field_name
   *   The state field machine name.
   *
   * @return \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface|null
   *   The state field item, or NULL when not found.
   */
  private function getStateItem(FieldableEntityInterface $entity, string $field_name): ?StateItemInterface {
    if (!$entity->hasField($field_name)) {
      return NULL;
    }
    $item = $entity->get($field_name)->first();
    return $item instanceof StateItemInterface ? $item : NULL;
  }

  /**
   * Loads a workflow plugin instance or returns NULL on failure.
   *
   * Centralises the try/catch pattern shared by getAllStateIds() and
   * getAllTransitionIds() so that adding a new plugin-level helper does not
   * require duplicating error handling.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowInterface|null
   *   The workflow instance, or NULL when the plugin does not exist.
   */
  private function loadWorkflow(string $workflow_id): ?WorkflowInterface {
    try {
      return $this->workflowManager->createInstance($workflow_id);
    }
    catch (PluginException) {
      return NULL;
    }
  }

  /**
   * Returns all state IDs for a workflow plugin, or [] on failure.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   *
   * @return string[]
   *   State machine names.
   */
  private function getAllStateIds(string $workflow_id): array {
    $workflow = $this->loadWorkflow($workflow_id);
    return $workflow !== NULL ? array_keys($workflow->getStates()) : [];
  }

  /**
   * Returns all transition IDs for a workflow plugin, or [] on failure.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   *
   * @return string[]
   *   Transition machine names.
   */
  private function getAllTransitionIds(string $workflow_id): array {
    $workflow = $this->loadWorkflow($workflow_id);
    return $workflow !== NULL ? array_keys($workflow->getTransitions()) : [];
  }

}
