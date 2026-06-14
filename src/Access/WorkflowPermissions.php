<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Access;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_ui\Service\TransitionAccessCheckerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates one Drupal permission per workflow × transition pair.
 *
 * Registered through state_machine_ui.permissions.yml as a permission
 * callback so that the dynamic IDs consumed by
 * {@see TransitionAccessCheckerInterface::filter()} show up on the
 * permission management page.
 */
final class WorkflowPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a WorkflowPermissions instance.
   *
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   Provides the available workflow plugin definitions and instances.
   * @param \Drupal\state_machine_ui\Service\TransitionAccessCheckerInterface $accessChecker
   *   Produces the canonical permission ID for a (workflow, transition) pair.
   */
  public function __construct(
    private readonly WorkflowManagerInterface $workflowManager,
    private readonly TransitionAccessCheckerInterface $accessChecker,
  ) {}

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.workflow'),
      $container->get(TransitionAccessCheckerInterface::class),
    );
  }

  /**
   * Builds the dynamic permissions array consumed by Drupal's permission system.
   *
   * Workflows that fail to instantiate are skipped silently so a single broken
   * plugin does not hide every other transition permission.
   *
   * @return array<string, array{title: \Drupal\Core\StringTranslation\TranslatableMarkup, description: \Drupal\Core\StringTranslation\TranslatableMarkup}>
   *   Permission IDs mapped to their title and description.
   */
  public function permissions(): array {
    $permissions = [];

    foreach ($this->workflowManager->getDefinitions() as $workflow_id => $definition) {
      $workflow_label = (string) ($definition['label'] ?? $workflow_id);
      try {
        $workflow = $this->workflowManager->createInstance($workflow_id);
      }
      catch (PluginException) {
        continue;
      }

      foreach ($workflow->getTransitions() as $transition_key => $transition) {
        $permissions[$this->accessChecker->permissionId($workflow_id, (string) $transition_key)] = [
          'title' => $this->t('Use transition %transition in workflow %workflow', [
            '%transition' => (string) $transition->getLabel(),
            '%workflow' => $workflow_label,
          ]),
          'description' => $this->t('Allows firing the %transition transition.', [
            '%transition' => (string) $transition->getLabel(),
          ]),
        ];
      }
    }

    return $permissions;
  }

}
