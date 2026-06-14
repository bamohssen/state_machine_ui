<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Entity\WorkflowMetadataSchema;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Entity\WorkflowTransition;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Parses metadata from WorkflowStateMachine config entities.
 *
 * Reads two independent metadata schemas on the workflow:
 *   - state_metadata_schema applied to each state's `fields` map,
 *   - transition_metadata_schema applied to each transition's `fields` map
 *     (loaded through WorkflowTransitionRepository).
 *
 * Priority 10 — runs before PluginMetadataParser (priority 0).
 *
 * @internal
 */
#[AutoconfigureTag('state_machine_ui.metadata_parser', ['priority' => 10])]
final readonly class EntityMetadataParser extends AbstractMetadataParser {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private EntityTypeManagerInterface $entityTypeManager,
    private WorkflowTransitionRepositoryInterface $transitionRepository,
    #[Autowire(service: 'logger.channel.state_machine_ui')]
    LoggerInterface $logger,
  ) {
    parent::__construct($logger);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function supports(string $workflow_id): bool {
    $entity = $this->entityTypeManager->getStorage(StateMachineUiConstants::ENTITY_WORKFLOW)->load($workflow_id);
    return $entity instanceof WorkflowStateMachine;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function parse(string $workflow_id): array {
    $result = ['states' => [], 'transitions' => []];

    $entity = $this->entityTypeManager->getStorage(StateMachineUiConstants::ENTITY_WORKFLOW)->load($workflow_id);
    if (!$entity instanceof WorkflowStateMachine) {
      return $result;
    }

    $result['states'] = $this->parseStates($entity);
    $result['transitions'] = $this->parseTransitions($entity);
    return $result;
  }

  /**
   * Extracts metadata declared on each state of the workflow.
   *
   * @return array<string, array<string, string[]>>
   *   state_key => field_key => list of normalised string values.
   */
  private function parseStates(WorkflowStateMachine $workflow): array {
    $schema_keys = $this->resolveSchemaKeys($workflow->getStateMetadataSchema());
    if ($schema_keys === []) {
      return [];
    }

    $out = [];
    foreach ($workflow->getStates() as $state) {
      $state_key = $state['key'] ?? '';
      if ($state_key === '') {
        continue;
      }
      $values = is_array($state['fields'] ?? NULL) ? $state['fields'] : [];
      $metadata = $this->extractFields($values, $schema_keys, "entity:{$workflow->id()}:state:{$state_key}");
      if ($metadata !== []) {
        $out[$state_key] = $metadata;
      }
    }
    return $out;
  }

  /**
   * Extracts metadata declared on each transition of the workflow.
   *
   * @return array<string, array<string, string[]>>
   *   transition_key => field_key => list of normalised string values.
   */
  private function parseTransitions(WorkflowStateMachine $workflow): array {
    $schema_keys = $this->resolveSchemaKeys($workflow->getTransitionMetadataSchema());
    if ($schema_keys === []) {
      return [];
    }

    $out = [];
    foreach ($this->transitionRepository->loadByWorkflow((string) $workflow->id()) as $transition) {
      assert($transition instanceof WorkflowTransition);
      $metadata = $this->extractFields(
        $transition->getFields(),
        $schema_keys,
        "entity:{$workflow->id()}:transition:{$transition->getKey()}",
      );
      if ($metadata !== []) {
        $out[$transition->getKey()] = $metadata;
      }
    }
    return $out;
  }

  /**
   * Returns the schema field keys for a given schema ID, or [] when missing.
   *
   * @return string[]
   *   Field key list.
   */
  private function resolveSchemaKeys(string $schema_id): array {
    if ($schema_id === '') {
      return [];
    }
    $schema = $this->entityTypeManager->getStorage(StateMachineUiConstants::ENTITY_SCHEMA)->load($schema_id);
    if (!$schema instanceof WorkflowMetadataSchema) {
      return [];
    }
    return array_column($schema->getFieldDefinitions(), 'key');
  }

  /**
   * Normalises field values for the subset of keys declared by the schema.
   *
   * @param array<string, mixed> $values
   *   Raw values keyed by field key.
   * @param string[] $schema_keys
   *   Allowed field keys from the schema.
   * @param string $context
   *   Logging context for non-conformant values.
   *
   * @return array<string, string[]>
   *   field_key => non-empty list of strings.
   */
  private function extractFields(array $values, array $schema_keys, string $context): array {
    $out = [];
    foreach ($schema_keys as $field_key) {
      if (!isset($values[$field_key])) {
        continue;
      }
      $normalized = $this->normalizeValues($values[$field_key]);
      if ($normalized === []) {
        continue;
      }
      $this->warnNonConformant($normalized, $field_key, $context);
      $out[$field_key] = $normalized;
    }
    return $out;
  }

}
