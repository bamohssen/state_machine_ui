<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Queries workflow states and transitions by metadata values.
 *
 * @api
 */
interface WorkflowMetadataQueryInterface {

  /**
   * Returns all state IDs in a workflow that have a specific metadata value.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID (e.g. 'article_publishing').
   * @param string $metadata_key
   *   The metadata field key defined in the schema (e.g. 'audience').
   * @param string $metadata_value
   *   The value to match (e.g. 'internal').
   *
   * @return string[]
   *   State machine names that carry the requested metadata value.
   *   Empty array when the workflow does not exist or no state matches.
   */
  public function getStatesByMetadata(
    string $workflow_id,
    string $metadata_key,
    string $metadata_value,
  ): array;

  /**
   * Returns all transition IDs in a workflow that have a specific metadata value.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   * @param string $metadata_key
   *   The metadata field key.
   * @param string $metadata_value
   *   The value to match.
   *
   * @return string[]
   *   Transition IDs that carry the requested metadata value.
   */
  public function getTransitionsByMetadata(
    string $workflow_id,
    string $metadata_key,
    string $metadata_value,
  ): array;

  /**
   * Returns the metadata values for a specific state.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   * @param string $state_id
   *   The state machine name.
   *
   * @return array<string, string[]>
   *   Metadata values indexed by key.
   *   Empty array when no metadata is defined for this state.
   */
  public function getStateMetadata(string $workflow_id, string $state_id): array;

  /**
   * Returns the metadata values for a specific transition.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   * @param string $transition_id
   *   The transition machine name.
   *
   * @return array<string, string[]>
   *   Metadata values indexed by key.
   */
  public function getTransitionMetadata(string $workflow_id, string $transition_id): array;

}
