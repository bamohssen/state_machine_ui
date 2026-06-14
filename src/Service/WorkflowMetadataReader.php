<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Reads custom metadata from State Machine workflow plugin definitions.
 *
 * Delegates to a prioritised list of MetadataParserInterface implementations
 * (injected via the state_machine_ui.metadata_parser tagged-iterator). The
 * first parser that supports a given workflow ID wins.
 *
 * Memoizes parsed results per workflow ID within the request.
 *
 * @internal
 */
final class WorkflowMetadataReader implements WorkflowMetadataReaderInterface {

  /**
   * Parsed cache indexed by workflow ID.
   *
   * @var array<string, array{states: array<string, array<string, string[]>>, transitions: array<string, array<string, string[]>>}>
   */
  private array $cache = [];

  /**
   * Constructs a WorkflowMetadataReader.
   *
   * @param iterable<\Drupal\state_machine_ui\Service\MetadataParserInterface> $parsers
   *   Ordered collection of metadata parsers (highest priority first).
   */
  public function __construct(
    #[AutowireIterator('state_machine_ui.metadata_parser')]
    private readonly iterable $parsers,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStateMetadata(string $workflow_id): array {
    $parsed = $this->parse($workflow_id);
    return $this->aggregate($parsed['states']);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionMetadata(string $workflow_id): array {
    $parsed = $this->parse($workflow_id);
    return $this->aggregate($parsed['transitions']);
  }

  /**
   * {@inheritdoc}
   */
  public function getStateValues(string $workflow_id, string $state_id): array {
    $parsed = $this->parse($workflow_id);
    return $parsed['states'][$state_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionValues(string $workflow_id, string $transition_id): array {
    $parsed = $this->parse($workflow_id);
    return $parsed['transitions'][$transition_id] ?? [];
  }

  /**
   * Parses and caches the metadata for a workflow.
   *
   * Iterates parsers in priority order and delegates to the first one that
   * supports the given workflow ID.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   *
   * @return array{states: array<string, array<string, string[]>>, transitions: array<string, array<string, string[]>>}
   *   Parsed metadata split by states and transitions.
   */
  private function parse(string $workflow_id): array {
    if (isset($this->cache[$workflow_id])) {
      return $this->cache[$workflow_id];
    }

    $result = ['states' => [], 'transitions' => []];

    foreach ($this->parsers as $parser) {
      if ($parser->supports($workflow_id)) {
        $result = $parser->parse($workflow_id);
        break;
      }
    }

    $this->cache[$workflow_id] = $result;
    return $result;
  }

  /**
   * Aggregates metadata values across all items (states or transitions).
   *
   * Merges per-item metadata into a single map, collecting unique string
   * values for each key across all items. Preserves insertion order.
   *
   * @param array<string, array<string, string[]>> $items
   *   Parsed metadata indexed by item ID (state key or transition key).
   *
   * @return array<string, string[]>
   *   Aggregated unique values per metadata key.
   */
  private function aggregate(array $items): array {
    $seen = [];

    foreach ($items as $item_metadata) {
      foreach ($item_metadata as $field_key => $values) {
        foreach ($values as $value) {
          $seen[$field_key][$value] = TRUE;
        }
      }
    }

    return array_map('array_keys', $seen);
  }

}
