<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine_ui\Constant\FilterLogic;

/**
 * Filters states and transitions based on metadata criteria.
 *
 * Intra-key logic: AND (item must have ALL checked values).
 * Inter-key logic: configurable AND or OR.
 *
 * @api
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
   * @param \Drupal\state_machine_ui\Constant\FilterLogic $logic
   *   Inter-key logic: AND or OR.
   *
   * @return string[]
   *   State IDs that pass the filters.
   */
  public function filterStates(string $workflow_id, array $state_ids, array $filters, FilterLogic $logic = FilterLogic::And): array;

  /**
   * Filters transition IDs based on metadata filters.
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   * @param string[] $transition_ids
   *   Transition IDs to filter.
   * @param array<string, string[]> $filters
   *   Filters keyed by metadata key, values are required values.
   * @param \Drupal\state_machine_ui\Constant\FilterLogic $logic
   *   Inter-key logic: AND or OR.
   *
   * @return string[]
   *   Transition IDs that pass the filters.
   */
  public function filterTransitions(string $workflow_id, array $transition_ids, array $filters, FilterLogic $logic = FilterLogic::And): array;

}
