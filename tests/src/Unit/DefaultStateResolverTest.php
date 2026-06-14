<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\Workflow\WorkflowState;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Service\DefaultStateResolver;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\state_machine_ui\Service\DefaultStateResolver
 * @group state_machine_ui
 */
final class DefaultStateResolverTest extends TestCase {

  /**
   * The mocked entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mocked entity storage handler.
   */
  private EntityStorageInterface $storage;

  /**
   * The service under test.
   */
  private DefaultStateResolver $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->method('getStorage')
      ->willReturn($this->storage);
    $this->resolver = new DefaultStateResolver($this->entityTypeManager);
  }

  /**
   * Builds a WorkflowState mock for a given ID.
   */
  private function makeState(string $id): WorkflowState {
    $state = $this->createMock(WorkflowState::class);
    $state->method('getId')->willReturn($id);
    return $state;
  }

  /**
   * Builds a WorkflowStateMachine entity mock.
   *
   * @param string $default_state
   *   Default state key returned by getDefaultState().
   * @param array<int, array{key: string, weight: int}> $states
   *   States returned by getStates().
   */
  private function makeEntity(string $default_state, array $states): WorkflowStateMachine {
    $entity = $this->createMock(WorkflowStateMachine::class);
    $entity->method('getDefaultState')->willReturn($default_state);
    $entity->method('getStates')->willReturn($states);
    return $entity;
  }

  /**
   * @covers ::getDefault
   */
  public function testReturnsExplicitDefaultStateWhenDeclared(): void {
    $entity = $this->makeEntity('review', [
      ['key' => 'draft', 'weight' => 0],
      ['key' => 'review', 'weight' => 1],
    ]);
    $this->storage->method('load')->willReturn($entity);

    $workflow = $this->createMock(Workflow::class);
    $workflow->method('getPluginId')->willReturn('article');
    $workflow->method('getState')->willReturnMap([
      ['draft', $this->makeState('draft')],
      ['review', $this->makeState('review')],
    ]);

    $this->assertSame('review', $this->resolver->getDefault($workflow));
  }

  /**
   * @covers ::getDefault
   */
  public function testFallsBackToLowestWeightStateWhenDefaultStateIsEmpty(): void {
    $entity = $this->makeEntity('', [
      ['key' => 'archived', 'weight' => 10],
      ['key' => 'draft', 'weight' => 0],
      ['key' => 'review', 'weight' => 5],
    ]);
    $this->storage->method('load')->willReturn($entity);

    $workflow = $this->createMock(Workflow::class);
    $workflow->method('getPluginId')->willReturn('article');
    $workflow->method('getState')->willReturnCallback(
      fn(string $id) => $this->makeState($id)
    );

    $this->assertSame('draft', $this->resolver->getDefault($workflow));
  }

  /**
   * @covers ::getDefault
   */
  public function testFallsBackToFirstPluginStateWhenNoEntityFound(): void {
    $this->storage->method('load')->willReturn(NULL);

    $draftState = $this->makeState('draft');
    $workflow = $this->createMock(Workflow::class);
    $workflow->method('getPluginId')->willReturn('yaml_workflow');
    $workflow->method('getStates')->willReturn(['draft' => $draftState]);

    $this->assertSame('draft', $this->resolver->getDefault($workflow));
  }

  /**
   * @covers ::getDefault
   */
  public function testReturnsNullWhenWorkflowHasNoStatesAtAll(): void {
    $this->storage->method('load')->willReturn(NULL);

    $workflow = $this->createMock(Workflow::class);
    $workflow->method('getPluginId')->willReturn('empty_workflow');
    $workflow->method('getStates')->willReturn([]);

    $this->assertNull($this->resolver->getDefault($workflow));
  }

  /**
   * @covers ::getDefault
   */
  public function testSkipsDefaultStateIfNotFoundInWorkflowPlugin(): void {
    $entity = $this->makeEntity('nonexistent', [
      ['key' => 'draft', 'weight' => 0],
    ]);
    $this->storage->method('load')->willReturn($entity);

    $workflow = $this->createMock(Workflow::class);
    $workflow->method('getPluginId')->willReturn('article');
    $workflow->method('getState')->willReturnMap([
      ['nonexistent', NULL],
      ['draft', $this->makeState('draft')],
    ]);

    // Should fall through to lowest-weight state.
    $this->assertSame('draft', $this->resolver->getDefault($workflow));
  }

  /**
   * @covers ::getDefault
   */
  public function testLowestWeightStateIsReturnedEvenIfNotFirst(): void {
    $entity = $this->makeEntity('', [
      ['key' => 'published', 'weight' => 2],
      ['key' => 'review', 'weight' => 1],
      ['key' => 'draft', 'weight' => 0],
    ]);
    $this->storage->method('load')->willReturn($entity);

    $workflow = $this->createMock(Workflow::class);
    $workflow->method('getPluginId')->willReturn('article');
    $workflow->method('getState')->willReturnCallback(
      fn(string $id) => $this->makeState($id)
    );

    $this->assertSame('draft', $this->resolver->getDefault($workflow));
  }

}
