<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\WorkflowGroup\WorkflowGroup;
use Drupal\state_machine_ui\Service\MermaidLibraryLocator;

/**
 * Hook implementations for state_machine_ui.
 *
 * Called by procedural hooks in state_machine_ui.module.
 *
 * D11.1+ migration: add #[Hook] attributes to each method and
 * #[LegacyHook] to the .module functions, then eventually remove .module.
 */
class StateMachineUiHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MermaidLibraryLocator $mermaidLocator,
  ) {}

  /**
   * Injects workflow group config entities into State Machine.
   *
   * @see state_machine_ui_workflow_groups_alter()
   */
  public function workflowGroupsAlter(array &$workflow_groups): void {
    /** @var \Drupal\state_machine_ui\Entity\WorkflowGroupConfig[] $groups */
    $groups = $this->entityTypeManager
      ->getStorage('workflow_group_config')
      ->loadMultiple();

    foreach ($groups as $group) {
      $group_id = (string) $group->id();
      // Don't override groups already declared in YAML by other modules.
      if (isset($workflow_groups[$group_id])) {
        continue;
      }
      $workflow_groups[$group_id] = [
        'id' => $group_id,
        'label' => (string) $group->label(),
        'entity_type' => (string) $group->get('entity_type'),
        'class' => WorkflowGroup::class,
        'workflow_class' => Workflow::class,
      ];
    }
  }

  /**
   * Injects workflow config entities into State Machine.
   *
   * @see state_machine_ui_workflows_alter()
   */
  public function workflowsAlter(array &$workflows): void {
    /** @var \Drupal\state_machine_ui\Entity\WorkflowStateMachine[] $entities */
    $entities = $this->entityTypeManager
      ->getStorage('workflow_state_machine')
      ->loadMultiple();

    foreach ($entities as $entity) {
      $workflow_id = (string) $entity->id();
      // Don't override workflows already declared in YAML by other modules.
      if (isset($workflows[$workflow_id])) {
        continue;
      }

      $group = (string) $entity->get('group');
      if ($group === '') {
        continue;
      }

      // Convert states: indexed array → keyed associative.
      $states = [];
      foreach ($entity->get('states') ?? [] as $state) {
        $key = $state['key'] ?? '';
        if ($key !== '') {
          $states[$key] = ['label' => $state['label'] ?? $key];
        }
      }

      // Convert transitions: indexed array → keyed associative.
      $transitions = [];
      foreach ($entity->get('transitions') ?? [] as $transition) {
        $key = $transition['key'] ?? '';
        if ($key !== '') {
          $transitions[$key] = [
            'label' => $transition['label'] ?? $key,
            'from' => $transition['from'] ?? [],
            'to' => $transition['to'] ?? '',
          ];
        }
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
   * @param string $phase
   *   The installation phase.
   *
   * @return array
   *   Requirements array for hook_requirements.
   *
   * @see state_machine_ui_requirements()
   */
  public function requirements(string $phase): array {
    $requirements = [];

    if ($phase !== 'runtime') {
      return $requirements;
    }

    $requirements['state_machine_ui_mermaid'] = [
      'title' => $this->t('State Machine UI — Mermaid.js'),
    ];

    if ($this->mermaidLocator->isInstalled()) {
      $requirements['state_machine_ui_mermaid']['value'] = $this->t('Installed');
      $requirements['state_machine_ui_mermaid']['severity'] = REQUIREMENT_OK;
    }
    else {
      $requirements['state_machine_ui_mermaid']['value'] = $this->t('Not installed');
      $requirements['state_machine_ui_mermaid']['severity'] = REQUIREMENT_INFO;
      $requirements['state_machine_ui_mermaid']['description'] = $this->t(
        'The Mermaid.js library is not installed. To enable workflow diagrams, download it and place <code>mermaid.min.js</code> in <code>@path</code>.',
        ['@path' => $this->mermaidLocator->getLibraryPath()],
      );
    }

    return $requirements;
  }

}
