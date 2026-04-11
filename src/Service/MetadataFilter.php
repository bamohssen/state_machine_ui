<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Filters states and transitions based on metadata criteria.
 *
 * Intra-key: AND — the item must have ALL checked values for a given key.
 * Inter-key: configurable — AND (must pass all keys) or OR (pass any key).
 */
final class MetadataFilter implements MetadataFilterInterface {

  public function __construct(
    protected readonly WorkflowMetadataReaderInterface $metadataReader,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function filterStates(string $workflow_id, array $state_ids, array $filters, string $logic = 'and'): array {
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
  public function filterTransitions(string $workflow_id, array $transition_ids, array $filters, string $logic = 'and'): array {
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
   *   The item's metadata (from the reader).
   * @param array<string, string[]> $filters
   *   The configured filters.
   * @param string $logic
   *   'and' or 'or' for inter-key logic.
   *
   * @return bool
   *   TRUE if the item passes the filters.
   */
  private function matchesFilters(array $item_metadata, array $filters, string $logic): bool {
    $results = [];

    foreach ($filters as $key => $required_values) {
      if (empty($required_values)) {
        continue;
      }
      $item_values = $item_metadata[$key] ?? [];
      // Intra-key AND: the item must have ALL required values.
      $results[] = $this->matchesIntraKey($item_values, $required_values);
    }

    if (empty($results)) {
      return TRUE;
    }

    // Inter-key logic.
    return $logic === 'or'
      ? in_array(TRUE, $results, TRUE)
      : !in_array(FALSE, $results, TRUE);
  }

  /**
   * Checks intra-key AND: item must contain ALL required values.
   *
   * @param string[] $item_values
   *   Values the item has for this key.
   * @param string[] $required_values
   *   Values the filter requires.
   *
   * @return bool
   *   TRUE if the item has all required values.
   */
  private function matchesIntraKey(array $item_values, array $required_values): bool {
    foreach ($required_values as $required) {
      if (!in_array($required, $item_values, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
