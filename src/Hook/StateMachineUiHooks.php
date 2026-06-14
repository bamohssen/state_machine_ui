<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\WorkflowGroup\WorkflowGroup;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Service\MermaidLibraryLocatorInterface;
use Drupal\state_machine_ui\Service\WorkflowTransitionRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for state_machine_ui.
 */
final readonly class StateMachineUiHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private EntityTypeManagerInterface $entityTypeManager,
    private MermaidLibraryLocatorInterface $mermaidLocator,
    #[Autowire(service: 'string_translation')]
    private TranslationInterface $translation,
    private WorkflowTransitionRepositoryInterface $transitionRepository,
  ) {}

  /**
   * Injects workflow group config entities into State Machine.
   *
   * Skips groups already declared in YAML by other modules to avoid overrides.
   */
  #[Hook('workflow_groups_alter')]
  public function workflowGroupsAlter(array &$workflow_groups): void {
    /** @var \Drupal\state_machine_ui\Entity\WorkflowGroupConfig[] $groups */
    $groups = $this->entityTypeManager
      ->getStorage(StateMachineUiConstants::ENTITY_GROUP)
      ->loadMultiple();

    foreach ($groups as $group) {
      $group_id = (string) $group->id();
      if (isset($workflow_groups[$group_id])) {
        continue;
      }
      $workflow_groups[$group_id] = [
        'id' => $group_id,
        'label' => (string) $group->label(),
        'entity_type' => $group->getWorkflowEntityType(),
        'class' => WorkflowGroup::class,
        'workflow_class' => Workflow::class,
      ];
    }
  }

  /**
   * Injects workflow config entities into State Machine.
   *
   * Converts the indexed-array storage format used by config entities into the
   * keyed-associative format expected by the State Machine plugin system.
   * Skips workflows already declared in YAML by other modules.
   */
  #[Hook('workflows_alter')]
  public function workflowsAlter(array &$workflows): void {
    /** @var \Drupal\state_machine_ui\Entity\WorkflowStateMachine[] $entities */
    $entities = $this->entityTypeManager
      ->getStorage(StateMachineUiConstants::ENTITY_WORKFLOW)
      ->loadMultiple();

    foreach ($entities as $entity) {
      $workflow_id = (string) $entity->id();
      if (isset($workflows[$workflow_id])) {
        continue;
      }

      $group = $entity->getGroup();
      if ($group === '') {
        continue;
      }

      $states = [];
      foreach ($entity->getStates() as $state) {
        $state_key = $state['key'] ?? '';
        if ($state_key !== '') {
          $states[$state_key] = ['label' => $state['label'] ?? $state_key];
        }
      }

      $transitions = [];
      foreach ($this->transitionRepository->loadByWorkflow($workflow_id) as $transition) {
        $transitions[$transition->getKey()] = [
          'label' => (string) $transition->label(),
          'from' => $transition->getFromStates(),
          'to' => $transition->getToState(),
        ];
      }

      if (empty($states) || empty($transitions)) {
        continue;
      }

      $workflows[$workflow_id] = [
        'id' => $workflow_id,
        'label' => (string) $entity->label(),
        'group' => $group,
        'states' => $states,
        'transitions' => $transitions,
      ];
    }
  }

  /**
   * Checks if the Mermaid.js library is installed.
   *
   * Called from state_machine_ui_requirements() in the .module file — Drupal
   * does not allow hook_requirements to be implemented as an OO hook.
   */
  public function requirements(string $phase): array {
    if ($phase !== 'runtime') {
      return [];
    }

    $t = $this->translation;
    $requirement = ['title' => $t->translate('State Machine UI — Mermaid.js')];

    if ($this->mermaidLocator->isInstalled()) {
      $requirement['value'] = $t->translate('Installed');
      $requirement['severity'] = REQUIREMENT_OK;
    }
    else {
      $requirement['value'] = $t->translate('Not installed');
      $requirement['severity'] = REQUIREMENT_INFO;
      $requirement['description'] = $t->translate(
        'The Mermaid.js library is not installed. To enable workflow diagrams, download it and place <code>mermaid.min.js</code> in <code>@path</code>.',
        ['@path' => $this->mermaidLocator->getLibraryPath()],
      );
    }

    return ['state_machine_ui_mermaid' => $requirement];
  }

}
