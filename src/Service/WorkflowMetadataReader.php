<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine\WorkflowManagerInterface;

/**
 * Reads custom metadata from State Machine workflow plugin definitions.
 *
 * Accesses raw YAML data via $workflow->getPluginDefinition() which preserves
 * all keys from the YAML, including those not used by WorkflowState/Transition.
 *
 * Memoizes parsed results per workflow ID within the request.
 */
final class WorkflowMetadataReader implements WorkflowMetadataReaderInterface {

  /**
   * Reserved keys that are not metadata.
   */
  private const STATE_RESERVED_KEYS = ['label'];
  private const TRANSITION_RESERVED_KEYS = ['label', 'from', 'to'];

  /**
   * Parsed cache: workflow_id => ['states' => [...], 'transitions' => [...]]
   *
   * @var array<string, array{states: array<string, array<string, string[]>>, transitions: array<string, array<string, string[]>>}>
   */
  private array $cache = [];

  public function __construct(
    protected readonly WorkflowManagerInterface $workflowManager,
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
   * Parses and caches the metadata from a workflow plugin definition.
   *
   * @return array{states: array<string, array<string, string[]>>, transitions: array<string, array<string, string[]>>}
   */
  private function parse(string $workflow_id): array {
    if (isset($this->cache[$workflow_id])) {
      return $this->cache[$workflow_id];
    }

    $result = ['states' => [], 'transitions' => []];

    try {
      $workflow = $this->workflowManager->createInstance($workflow_id);
    }
    catch (\Exception) {
      $this->cache[$workflow_id] = $result;
      return $result;
    }

    $definition = $workflow->getPluginDefinition();

    // Parse states.
    foreach ($definition['states'] ?? [] as $state_id => $state_def) {
      $metadata = $this->extractMetadata($state_def, self::STATE_RESERVED_KEYS);
      if (!empty($metadata)) {
        $result['states'][$state_id] = $metadata;
      }
    }

    // Parse transitions.
    foreach ($definition['transitions'] ?? [] as $transition_id => $transition_def) {
      $metadata = $this->extractMetadata($transition_def, self::TRANSITION_RESERVED_KEYS);
      if (!empty($metadata)) {
        $result['transitions'][$transition_id] = $metadata;
      }
    }

    $this->cache[$workflow_id] = $result;
    return $result;
  }

  /**
   * Extracts metadata keys from a definition, excluding reserved keys.
   *
   * Normalizes scalar values to single-element arrays.
   *
   * @param array $definition
   *   The raw state or transition definition from YAML.
   * @param string[] $reserved_keys
   *   Keys to exclude.
   *
   * @return array<string, string[]>
   *   Normalized metadata.
   */
  private function extractMetadata(array $definition, array $reserved_keys): array {
    $metadata = [];

    foreach ($definition as $key => $value) {
      if (in_array($key, $reserved_keys, TRUE)) {
        continue;
      }
      // Normalize scalar to array.
      $metadata[$key] = is_array($value)
        ? array_map('strval', array_values($value))
        : [(string) $value];
    }

    return $metadata;
  }

  /**
   * Aggregates metadata values across all items (states or transitions).
   *
   * Collects unique values for each key across all items.
   *
   * @param array<string, array<string, string[]>> $items
   *   Parsed metadata per item ID.
   *
   * @return array<string, string[]>
   *   Aggregated unique values per key.
   */
  private function aggregate(array $items): array {
    $aggregated = [];

    foreach ($items as $item_metadata) {
      foreach ($item_metadata as $key => $values) {
        if (!isset($aggregated[$key])) {
          $aggregated[$key] = [];
        }
        foreach ($values as $value) {
          if (!in_array($value, $aggregated[$key], TRUE)) {
            $aggregated[$key][] = $value;
          }
        }
      }
    }

    return $aggregated;
  }

}
