<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine_ui\Constant\FilterLogic;

/**
 * Filters states and transitions based on metadata criteria.
 *
 * Intra-key: AND — the item must have ALL checked values for a given key.
 * Inter-key: configurable — AND (must pass all keys) or OR (pass any key).
 *
 * @internal
 */
final readonly class MetadataFilter implements MetadataFilterInterface {

  /**
   * Constructs a MetadataFilter instance.
   *
   * @param \Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface $metadataReader
   *   The workflow metadata reader.
   */
  public function __construct(
    private WorkflowMetadataReaderInterface $metadataReader,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function filterStates(string $workflow_id, array $state_ids, array $filters, FilterLogic $logic = FilterLogic::And): array {
    if (empty($filters)) {
      return $state_ids;
    }

    return array_values(array_filter(
      $state_ids,
      fn(string $state_id): bool => $this->matchesFilters(
        $this->metadataReader->getStateValues($workflow_id, $state_id),
        $filters,
        $logic,
      ),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function filterTransitions(string $workflow_id, array $transition_ids, array $filters, FilterLogic $logic = FilterLogic::And): array {
    if (empty($filters)) {
      return $transition_ids;
    }

    return array_values(array_filter(
      $transition_ids,
      fn(string $transition_id): bool => $this->matchesFilters(
        $this->metadataReader->getTransitionValues($workflow_id, $transition_id),
        $filters,
        $logic,
      ),
    ));
  }

  /**
   * Checks if an item's metadata matches the filters.
   *
   * @param array<string, string[]> $item_metadata
   *   The item's metadata values indexed by key.
   * @param array<string, string[]> $filters
   *   The configured filters indexed by metadata key.
   * @param \Drupal\state_machine_ui\Constant\FilterLogic $logic
   *   Inter-key logic.
   *
   * @return bool
   *   TRUE if the item passes the filters.
   */
  private function matchesFilters(array $item_metadata, array $filters, FilterLogic $logic): bool {
    $results = [];

    foreach ($filters as $metadata_key => $required_values) {
      if (empty($required_values)) {
        continue;
      }
      $item_values = $item_metadata[$metadata_key] ?? [];
      $results[] = $this->matchesIntraKey($item_values, $required_values);
    }

    if (empty($results)) {
      return TRUE;
    }

    return $logic === FilterLogic::Or
      ? in_array(TRUE, $results, TRUE)
      : !in_array(FALSE, $results, TRUE);
  }

  /**
   * Checks intra-key AND: item must contain ALL required values.
   *
   * @param string[] $item_values
   *   Values the item has for this metadata key.
   * @param string[] $required_values
   *   Values the filter requires for this key.
   *
   * @return bool
   *   TRUE if the item has all required values.
   */
  private function matchesIntraKey(array $item_values, array $required_values): bool {
    foreach ($required_values as $required_value) {
      if (!in_array($required_value, $item_values, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
