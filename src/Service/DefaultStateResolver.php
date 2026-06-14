<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Default implementation of DefaultStateResolverInterface.
 *
 * Reads the `default_state` and `states` from the WorkflowStateMachine entity
 * when available, otherwise falls back to the first state from the plugin.
 *
 * @internal
 */
final readonly class DefaultStateResolver implements DefaultStateResolverInterface {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getDefault(WorkflowInterface $workflow): ?string {
    $workflow_id = $workflow->getPluginId();

    $entity = $this->entityTypeManager
      ->getStorage(StateMachineUiConstants::ENTITY_WORKFLOW)
      ->load($workflow_id);

    if ($entity instanceof WorkflowStateMachine) {
      // 1. Explicit default_state declared on the entity.
      $default = $entity->getDefaultState();
      if ($default !== '' && $workflow->getState($default) !== NULL) {
        return $default;
      }

      // 2. Lowest-weight state from the entity's ordered list.
      $states = $entity->getStates();
      usort($states, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
      foreach ($states as $state) {
        $key = $state['key'] ?? '';
        if ($key !== '' && $workflow->getState($key) !== NULL) {
          return $key;
        }
      }
    }

    // 3. Last resort: first state from the plugin itself (YAML-declared workflows).
    $all_states = $workflow->getStates();
    if ($all_states === []) {
      return NULL;
    }
    $first = reset($all_states);
    return $first ? $first->getId() : NULL;
  }

}
