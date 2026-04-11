<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Filters states and transitions based on metadata criteria.
 *
 * Intra-key logic: AND (item must have ALL checked values).
 * Inter-key logic: configurable AND or OR.
 */
interface MetadataFilterInterface {

  /**
   * Filters state IDs based on metadata filters.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   * @param string[] $state_ids
   *   State IDs to filter.
   * @param array<string, string[]> $filters
   *   Filters keyed by metadata key, values are required values.
   * @param string $logic
   *   Inter-key logic: 'and' or 'or'.
   *
   * @return string[]
   *   State IDs that pass the filters.
   */
  public function filterStates(string $workflow_id, array $state_ids, array $filters, string $logic = 'and'): array;

  /**
   * Filters transition IDs based on metadata filters.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   * @param string[] $transition_ids
   *   Transition IDs to filter.
   * @param array<string, string[]> $filters
   *   Filters keyed by metadata key, values are required values.
   * @param string $logic
   *   Inter-key logic: 'and' or 'or'.
   *
   * @return string[]
   *   Transition IDs that pass the filters.
   */
  public function filterTransitions(string $workflow_id, array $transition_ids, array $filters, string $logic = 'and'): array;

}
