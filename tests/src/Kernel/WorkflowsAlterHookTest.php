<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\state_machine_ui\Entity\WorkflowGroupConfig;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Entity\WorkflowTransition;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers hook_workflows_alter() and hook_workflow_groups_alter().
 *
 * Verifies that the OO hook classes inject the module's config entities
 * into the State Machine plugin system at runtime.
 *
 * @group state_machine_ui
 */
#[RunTestsInSeparateProcesses]
final class WorkflowsAlterHookTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'state_machine',
    'state_machine_ui',
  ];

  /**
   * Group + workflow + 2 transitions are exposed to the plugin manager.
   */
  public function testEntitiesAreExposedAsPlugins(): void {
    WorkflowGroupConfig::create([
      'id' => 'editorial_group',
      'label' => 'Editorial',
      'entity_type' => 'node',
    ])->save();

    WorkflowStateMachine::create([
      'id' => 'article_publishing',
      'label' => 'Article publishing',
      'group' => 'editorial_group',
      'default_state' => 'draft',
      'states' => [
        ['key' => 'draft', 'label' => 'Draft', 'description' => '', 'weight' => 0, 'fields' => []],
        ['key' => 'review', 'label' => 'In review', 'description' => '', 'weight' => 1, 'fields' => []],
        ['key' => 'published', 'label' => 'Published', 'description' => '', 'weight' => 2, 'fields' => []],
      ],
    ])->save();

    WorkflowTransition::create([
      'id' => WorkflowTransition::buildId('article_publishing', 'submit'),
      'label' => 'Submit for review',
      'workflow' => 'article_publishing',
      'key' => 'submit',
      'from' => ['draft'],
      'to' => 'review',
      'weight' => 0,
    ])->save();

    WorkflowTransition::create([
      'id' => WorkflowTransition::buildId('article_publishing', 'approve'),
      'label' => 'Approve',
      'workflow' => 'article_publishing',
      'key' => 'approve',
      'from' => ['review'],
      'to' => 'published',
      'weight' => 1,
    ])->save();

    /** @var \Drupal\state_machine\WorkflowGroupManagerInterface $group_manager */
    $group_manager = $this->container->get('plugin.manager.workflow_group');
    $group_manager->clearCachedDefinitions();
    $this->assertArrayHasKey('editorial_group', $group_manager->getDefinitions());

    /** @var \Drupal\state_machine\WorkflowManagerInterface $workflow_manager */
    $workflow_manager = $this->container->get('plugin.manager.workflow');
    $workflow_manager->clearCachedDefinitions();
    $definitions = $workflow_manager->getDefinitions();
    $this->assertArrayHasKey('article_publishing', $definitions);

    $workflow = $workflow_manager->createInstance('article_publishing');
    $transitions = $workflow->getTransitions();
    $this->assertCount(2, $transitions);
    $this->assertArrayHasKey('submit', $transitions);
    $this->assertArrayHasKey('approve', $transitions);
    $this->assertSame('review', $transitions['submit']->getToState()->getId());
    $this->assertSame('published', $transitions['approve']->getToState()->getId());
  }

  /**
   * Workflows with no transitions are excluded from the plugin manager.
   */
  public function testWorkflowWithoutTransitionsIsSkipped(): void {
    WorkflowGroupConfig::create([
      'id' => 'empty_group',
      'label' => 'Empty',
      'entity_type' => 'node',
    ])->save();

    WorkflowStateMachine::create([
      'id' => 'incomplete',
      'label' => 'Incomplete',
      'group' => 'empty_group',
      'default_state' => 'draft',
      'states' => [
        ['key' => 'draft', 'label' => 'Draft', 'description' => '', 'weight' => 0, 'fields' => []],
      ],
    ])->save();

    $workflow_manager = $this->container->get('plugin.manager.workflow');
    $workflow_manager->clearCachedDefinitions();
    $this->assertArrayNotHasKey('incomplete', $workflow_manager->getDefinitions());
  }

}
