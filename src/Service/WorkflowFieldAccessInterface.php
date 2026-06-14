<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;

/**
 * Provides basic field-level access to a workflow for a given entity.
 *
 * @api
 */
interface WorkflowFieldAccessInterface {

  /**
   * Returns the workflow plugin for a given entity field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity whose state field is being queried.
   * @param string $field_name
   *   The machine name of the state field (e.g. 'field_status').
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowInterface|null
   *   The workflow plugin, or NULL when the field is missing or not a state
   *   field.
   */
  public function getWorkflow(FieldableEntityInterface $entity, string $field_name): ?WorkflowInterface;

  /**
   * Returns the current state ID for a given entity field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to inspect.
   * @param string $field_name
   *   The machine name of the state field.
   *
   * @return string|null
   *   The current state machine name, or NULL when unknown.
   */
  public function getCurrentStateId(FieldableEntityInterface $entity, string $field_name): ?string;

  /**
   * Returns all transitions currently available for an entity field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to inspect.
   * @param string $field_name
   *   The machine name of the state field.
   *
   * @return array<string, \Drupal\state_machine\Plugin\Workflow\WorkflowTransition>
   *   Keyed by transition ID. Empty array when no transition is available or
   *   the field is not found.
   */
  public function getAvailableTransitions(FieldableEntityInterface $entity, string $field_name): array;

}
