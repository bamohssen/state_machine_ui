<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\state_machine\WorkflowManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Parses metadata from YAML-declared workflow plugin definitions.
 *
 * Fallback parser (priority 0). Used for any workflow that is not managed
 * as a WorkflowStateMachine config entity.
 *
 * @internal
 */
#[AutoconfigureTag('state_machine_ui.metadata_parser', ['priority' => 0])]
final readonly class PluginMetadataParser extends AbstractMetadataParser {

  private const array STATE_RESERVED_KEYS      = ['label'];
  private const array TRANSITION_RESERVED_KEYS = ['label', 'from', 'to'];

  public function __construct(
    #[Autowire(service: 'plugin.manager.workflow')]
    private WorkflowManagerInterface $workflowManager,
    #[Autowire(service: 'logger.channel.state_machine_ui')]
    LoggerInterface $logger,
  ) {
    parent::__construct($logger);
  }

  /**
   * {@inheritdoc}
   *
   * Always returns TRUE — this is the catch-all fallback.
   */
  #[\Override]
  public function supports(string $workflow_id): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function parse(string $workflow_id): array {
    $result = ['states' => [], 'transitions' => []];

    try {
      $workflow = $this->workflowManager->createInstance($workflow_id);
    }
    catch (PluginException) {
      return $result;
    }

    $definition = $workflow->getPluginDefinition();

    foreach ($definition['states'] ?? [] as $state_id => $state_definition) {
      $state_metadata = $this->extractMetadata(
        $state_definition,
        self::STATE_RESERVED_KEYS,
        "{$workflow_id}:state:{$state_id}"
      );
      if (!empty($state_metadata)) {
        $result['states'][$state_id] = $state_metadata;
      }
    }

    foreach ($definition['transitions'] ?? [] as $transition_id => $transition_definition) {
      $transition_metadata = $this->extractMetadata(
        $transition_definition,
        self::TRANSITION_RESERVED_KEYS,
        "{$workflow_id}:transition:{$transition_id}"
      );
      if (!empty($transition_metadata)) {
        $result['transitions'][$transition_id] = $transition_metadata;
      }
    }

    return $result;
  }

  /**
   * Extracts metadata keys from a definition, excluding reserved keys.
   *
   * @param array<string, mixed> $definition
   *   The raw state or transition definition from the plugin YAML.
   * @param string[] $reserved_keys
   *   Keys to exclude from the result.
   * @param string $context
   *   Human-readable context string used in warning log messages.
   *
   * @return array<string, string[]>
   *   Normalized metadata: each key maps to a non-empty list of string values.
   */
  private function extractMetadata(array $definition, array $reserved_keys, string $context): array {
    $metadata = [];

    foreach ($definition as $field_key => $raw_value) {
      if (in_array($field_key, $reserved_keys, TRUE)) {
        continue;
      }

      $normalized_values = $this->normalizeValues($raw_value);
      if (empty($normalized_values)) {
        continue;
      }

      $this->warnNonConformant($normalized_values, $field_key, $context);
      $metadata[$field_key] = $normalized_values;
    }

    return $metadata;
  }

}
