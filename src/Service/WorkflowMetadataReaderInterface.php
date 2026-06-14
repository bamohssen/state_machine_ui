<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Reads custom metadata from State Machine workflow plugin definitions.
 *
 * Metadata are all keys in the YAML that are not reserved by State Machine.
 * States reserved keys: 'label'.
 * Transitions reserved keys: 'label', 'from', 'to'.
 *
 * @api
 */
interface WorkflowMetadataReaderInterface {

  /**
   * Gets aggregated metadata for all states of a workflow.
   *
   * Returns all custom keys found across all states, with unique values
   * collected from every state that declares that key.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   *
   * @return array<string, string[]>
   *   Metadata keyed by key name, values are unique aggregated values.
   *   Example: ['tag' => ['can_be_edited', 'need_review'], 'category' => ['article', 'book']]
   */
  public function getStateMetadata(string $workflow_id): array;

  /**
   * Gets aggregated metadata for all transitions of a workflow.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   *
   * @return array<string, string[]>
   *   Metadata keyed by key name, values are unique aggregated values.
   */
  public function getTransitionMetadata(string $workflow_id): array;

  /**
   * Gets the metadata values for a specific state.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   * @param string $state_id
   *   The state ID.
   *
   * @return array<string, string[]>
   *   Metadata for this state, normalized (scalars as single-element arrays).
   */
  public function getStateValues(string $workflow_id, string $state_id): array;

  /**
   * Gets the metadata values for a specific transition.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   * @param string $transition_id
   *   The transition ID.
   *
   * @return array<string, string[]>
   *   Metadata for this transition, normalized.
   */
  public function getTransitionValues(string $workflow_id, string $transition_id): array;

}
