<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Kernel;

use Drupal\Core\Config\ConfigValueException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Entity\WorkflowGroupConfig;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Entity\WorkflowTransition;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the WorkflowTransition config entity lifecycle.
 *
 * Focus areas:
 *   - composite ID builder
 *   - preSave referential integrity (workflow + states)
 *   - dependency cascade on workflow deletion.
 *
 * @group state_machine_ui
 */
#[RunTestsInSeparateProcesses]
final class WorkflowTransitionEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'state_machine',
    'state_machine_ui',
  ];

  /**
   * Convenience: a saved workflow with two states (draft, published).
   */
  private WorkflowStateMachine $workflow;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    WorkflowGroupConfig::create([
      'id' => 'test_group',
      'label' => 'Test',
      'entity_type' => 'node',
    ])->save();

    $this->workflow = WorkflowStateMachine::create([
      'id' => 'editorial',
      'label' => 'Editorial',
      'group' => 'test_group',
      'default_state' => 'draft',
      'states' => [
        ['key' => 'draft', 'label' => 'Draft', 'description' => '', 'weight' => 0, 'fields' => []],
        ['key' => 'published', 'label' => 'Published', 'description' => '', 'weight' => 1, 'fields' => []],
      ],
    ]);
    $this->workflow->save();
  }

  /**
   * @covers \Drupal\state_machine_ui\Entity\WorkflowTransition::buildId
   */
  public function testBuildIdReturnsCompositeId(): void {
    $id = WorkflowTransition::buildId('editorial', 'publish');
    $this->assertSame('editorial__publish', $id);
  }

  /**
   * Saving a valid transition succeeds and round-trips its data.
   */
  public function testValidTransitionSavesAndLoads(): void {
    WorkflowTransition::create([
      'id' => WorkflowTransition::buildId('editorial', 'publish'),
      'label' => 'Publish',
      'workflow' => 'editorial',
      'key' => 'publish',
      'from' => ['draft'],
      'to' => 'published',
      'weight' => 0,
    ])->save();

    $loaded = WorkflowTransition::load('editorial__publish');
    $this->assertNotNull($loaded);
    $this->assertSame('editorial', $loaded->getWorkflowId());
    $this->assertSame('publish', $loaded->getKey());
    $this->assertSame(['draft'], $loaded->getFromStates());
    $this->assertSame('published', $loaded->getToState());
  }

  /**
   * The preSave check refuses a transition whose 'workflow' is empty.
   */
  public function testPreSaveRejectsMissingWorkflow(): void {
    $this->expectException(ConfigValueException::class);
    $this->expectExceptionMessage('must reference a parent workflow');

    WorkflowTransition::create([
      'id' => '__publish',
      'label' => 'Publish',
      'workflow' => '',
      'key' => 'publish',
      'from' => ['draft'],
      'to' => 'published',
    ])->save();
  }

  /**
   * The preSave check refuses a transition referencing an unknown state.
   */
  public function testPreSaveRejectsUnknownStateReference(): void {
    $this->expectException(ConfigValueException::class);
    $this->expectExceptionMessage('references unknown state');

    WorkflowTransition::create([
      'id' => WorkflowTransition::buildId('editorial', 'go_nowhere'),
      'label' => 'Go nowhere',
      'workflow' => 'editorial',
      'key' => 'go_nowhere',
      'from' => ['draft'],
      'to' => 'nonexistent_state',
    ])->save();
  }

  /**
   * The preSave check refuses a transition whose ID does not match its key.
   */
  public function testPreSaveRejectsMismatchedCompositeId(): void {
    $this->expectException(ConfigValueException::class);
    $this->expectExceptionMessage('does not match its workflow/key pair');

    WorkflowTransition::create([
      'id' => 'editorial__something_else',
      'label' => 'Publish',
      'workflow' => 'editorial',
      'key' => 'publish',
      'from' => ['draft'],
      'to' => 'published',
    ])->save();
  }

  /**
   * Deleting the parent workflow cascades to its transitions.
   */
  public function testWorkflowDeletionCascadesToTransitions(): void {
    WorkflowTransition::create([
      'id' => WorkflowTransition::buildId('editorial', 'publish'),
      'label' => 'Publish',
      'workflow' => 'editorial',
      'key' => 'publish',
      'from' => ['draft'],
      'to' => 'published',
      'weight' => 0,
    ])->save();

    $this->assertInstanceOf(WorkflowTransition::class, WorkflowTransition::load('editorial__publish'));

    $this->workflow->delete();

    $this->assertNull(
      \Drupal::entityTypeManager()
        ->getStorage(StateMachineUiConstants::ENTITY_TRANSITION)
        ->load('editorial__publish'),
      'Transition should be removed when its parent workflow is deleted.',
    );
  }

}
