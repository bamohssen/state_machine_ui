<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\state_machine_ui\Entity\WorkflowGroupConfig;
use Drupal\state_machine_ui\Entity\WorkflowMetadataSchema;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Entity\WorkflowTransition;
use Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the transition metadata pipeline.
 *
 * Ensures the new transition_metadata_schema reference on the workflow
 * and the fields property on WorkflowTransition are persisted and that
 * EntityMetadataParser surfaces them through WorkflowMetadataReader.
 *
 * @group state_machine_ui
 */
#[RunTestsInSeparateProcesses]
final class WorkflowTransitionMetadataTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'state_machine',
    'state_machine_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    WorkflowGroupConfig::create([
      'id' => 'editorial',
      'label' => 'Editorial',
      'entity_type' => 'node',
    ])->save();

    WorkflowMetadataSchema::create([
      'id' => 'transition_schema',
      'label' => 'Transition schema',
      'field_definitions' => [
        ['key' => 'category', 'label' => 'Category', 'type' => 'list', 'description' => ''],
        ['key' => 'urgency', 'label' => 'Urgency', 'type' => 'string', 'description' => ''],
      ],
    ])->save();
  }

  /**
   * Round-trip: fields are persisted and read back by the parser/reader.
   */
  public function testTransitionMetadataRoundTrip(): void {
    WorkflowStateMachine::create([
      'id' => 'editorial_flow',
      'label' => 'Editorial flow',
      'group' => 'editorial',
      'default_state' => 'draft',
      'transition_metadata_schema' => 'transition_schema',
      'states' => [
        ['key' => 'draft', 'label' => 'Draft', 'description' => '', 'weight' => 0, 'fields' => []],
        ['key' => 'published', 'label' => 'Published', 'description' => '', 'weight' => 1, 'fields' => []],
      ],
    ])->save();

    WorkflowTransition::create([
      'id' => WorkflowTransition::buildId('editorial_flow', 'publish'),
      'label' => 'Publish',
      'workflow' => 'editorial_flow',
      'key' => 'publish',
      'from' => ['draft'],
      'to' => 'published',
      'weight' => 0,
      'fields' => [
        'category' => ['editorial', 'public'],
        'urgency' => 'high',
      ],
    ])->save();

    $loaded = WorkflowTransition::load('editorial_flow__publish');
    $this->assertSame(['editorial', 'public'], $loaded->getFields()['category']);
    $this->assertSame('high', $loaded->getFields()['urgency']);

    /** @var \Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface $reader */
    $reader = $this->container->get(WorkflowMetadataReaderInterface::class);
    $values = $reader->getTransitionValues('editorial_flow', 'publish');
    $this->assertSame(['editorial', 'public'], $values['category']);
    $this->assertSame(['high'], $values['urgency']);

    $aggregate = $reader->getTransitionMetadata('editorial_flow');
    $this->assertContains('editorial', $aggregate['category']);
    $this->assertContains('public', $aggregate['category']);
    $this->assertContains('high', $aggregate['urgency']);
  }

  /**
   * Without a transition_metadata_schema, the parser yields no values.
   */
  public function testNoSchemaYieldsNoTransitionMetadata(): void {
    WorkflowStateMachine::create([
      'id' => 'no_schema_flow',
      'label' => 'No schema',
      'group' => 'editorial',
      'default_state' => 'draft',
      'states' => [
        ['key' => 'draft', 'label' => 'Draft', 'description' => '', 'weight' => 0, 'fields' => []],
        ['key' => 'done', 'label' => 'Done', 'description' => '', 'weight' => 1, 'fields' => []],
      ],
    ])->save();

    WorkflowTransition::create([
      'id' => WorkflowTransition::buildId('no_schema_flow', 'finish'),
      'label' => 'Finish',
      'workflow' => 'no_schema_flow',
      'key' => 'finish',
      'from' => ['draft'],
      'to' => 'done',
      'weight' => 0,
      'fields' => [
        'category' => ['ignored'],
      ],
    ])->save();

    /** @var \Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface $reader */
    $reader = $this->container->get(WorkflowMetadataReaderInterface::class);
    $this->assertSame([], $reader->getTransitionValues('no_schema_flow', 'finish'));
    $this->assertSame([], $reader->getTransitionMetadata('no_schema_flow'));
  }

}
