<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Entity\WorkflowTransition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Default implementation of WorkflowTransitionRepositoryInterface.
 *
 * Memoizes loadByWorkflow() results per request to skip repeated
 * loadByProperties() + sort calls. Entity payloads themselves are
 * already memoized by core's config storage.
 *
 * @internal
 */
final class WorkflowTransitionRepository implements WorkflowTransitionRepositoryInterface {

  /**
   * Per-request memoization of loadByWorkflow() results.
   *
   * @var array<string, array<string, \Drupal\state_machine_ui\Entity\WorkflowTransition>>
   */
  private array $byWorkflow = [];

  /**
   * Constructs a WorkflowTransitionRepository instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Provides the workflow_transition storage handler.
   */
  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function loadByWorkflow(string $workflow_id): array {
    if (isset($this->byWorkflow[$workflow_id])) {
      return $this->byWorkflow[$workflow_id];
    }

    $storage = $this->entityTypeManager->getStorage(StateMachineUiConstants::ENTITY_TRANSITION);
    /** @var \Drupal\state_machine_ui\Entity\WorkflowTransition[] $transitions */
    $transitions = $storage->loadByProperties(['workflow' => $workflow_id]);

    uasort(
      $transitions,
      static fn(WorkflowTransition $a, WorkflowTransition $b): int => $a->getWeight() <=> $b->getWeight(),
    );

    return $this->byWorkflow[$workflow_id] = $transitions;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function findReferencingStates(string $workflow_id, array $state_keys): array {
    if ($state_keys === []) {
      return [];
    }
    $lookup = array_flip($state_keys);

    return array_filter(
      $this->loadByWorkflow($workflow_id),
      static function (WorkflowTransition $transition) use ($lookup): bool {
        if (isset($lookup[$transition->getToState()])) {
          return TRUE;
        }
        foreach ($transition->getFromStates() as $from) {
          if (isset($lookup[$from])) {
            return TRUE;
          }
        }
        return FALSE;
      },
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function loadByKey(string $workflow_id, string $key): ?WorkflowTransition {
    $id = WorkflowTransition::buildId($workflow_id, $key);
    $entity = $this->entityTypeManager
      ->getStorage(StateMachineUiConstants::ENTITY_TRANSITION)
      ->load($id);
    return $entity instanceof WorkflowTransition ? $entity : NULL;
  }

}
