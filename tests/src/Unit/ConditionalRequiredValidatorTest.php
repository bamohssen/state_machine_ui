<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Unit;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_ui\Service\ConditionalFieldResolverInterface;
use Drupal\state_machine_ui\Validation\ConditionalRequiredValidator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\state_machine_ui\Validation\ConditionalRequiredValidator
 * @group state_machine_ui
 */
final class ConditionalRequiredValidatorTest extends TestCase {

  /**
   * The mocked conditional field resolver.
   */
  private ConditionalFieldResolverInterface $resolver;

  /**
   * The mocked workflow manager.
   */
  private WorkflowManagerInterface $workflowManager;

  /**
   * The service under test.
   */
  private ConditionalRequiredValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resolver = $this->createMock(ConditionalFieldResolverInterface::class);
    $this->workflowManager = $this->createMock(WorkflowManagerInterface::class);

    $this->validator = new ConditionalRequiredValidator($this->resolver, $this->workflowManager);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnArgument(0);
    $this->validator->setStringTranslation($translation);
  }

  /**
   * Builds a minimal form state with a target state value.
   */
  private function makeFormState(string $target_state, ?EntityFormInterface $form_object = NULL): FormStateInterface {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->willReturnMap([
        ['field_status', NULL, [0 => ['value' => $target_state]]],
      ]);
    $form_state->method('getFormObject')->willReturn($form_object);
    return $form_state;
  }

  /**
   * @covers ::validate
   */
  public function testReturnsEarlyWhenNoStateMachineUiConfig(): void {
    $element = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $this->resolver->expects($this->never())->method('getRequiredFields');

    $this->validator->validate($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validate
   */
  public function testReturnsEarlyWhenConditionsIsEmpty(): void {
    $element = ['#state_machine_ui' => ['conditions' => [], 'state_field_name' => 'field_status']];
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $this->resolver->expects($this->never())->method('getRequiredFields');

    $this->validator->validate($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validate
   */
  public function testReturnsEarlyWhenStateFieldNameIsEmpty(): void {
    $element = [
      '#state_machine_ui' => [
        'conditions' => [['field' => 'body', 'state' => 'published', 'visibility' => 'show']],
        'state_field_name' => '',
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $this->resolver->expects($this->never())->method('getRequiredFields');

    $this->validator->validate($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validate
   */
  public function testReturnsEarlyWhenTargetStateIsEmpty(): void {
    $element = [
      '#state_machine_ui' => [
        'conditions' => [['field' => 'body', 'state' => 'published', 'visibility' => 'show']],
        'state_field_name' => 'field_status',
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->willReturn(NULL);
    $complete_form = [];

    $this->resolver->expects($this->never())->method('getRequiredFields');

    $this->validator->validate($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validate
   */
  public function testNoErrorWhenNoFieldsAreRequired(): void {
    $element = [
      '#state_machine_ui' => [
        'conditions' => [['field' => 'body', 'state' => 'published', 'visibility' => 'show', 'required' => FALSE]],
        'state_field_name' => 'field_status',
      ],
    ];

    $this->resolver->method('getRequiredFields')->willReturn([]);

    $form_state = $this->makeFormState('published');
    $form_state->expects($this->never())->method('setErrorByName');
    $complete_form = [];

    $this->validator->validate($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validate
   */
  public function testSetsErrorWhenRequiredFieldIsEmpty(): void {
    $conditions = [['field' => 'body', 'state' => 'published', 'visibility' => 'show', 'required' => TRUE]];
    $element = [
      '#state_machine_ui' => [
        'conditions' => $conditions,
        'state_field_name' => 'field_status',
      ],
    ];

    $this->resolver->method('getRequiredFields')->with($conditions, 'published')->willReturn(['body']);

    // Build a minimal entity mock.
    $field_list = $this->createMock(FieldItemListInterface::class);
    $field_list->method('isEmpty')->willReturn(TRUE);
    $field_list->method('setValue')->willReturn(NULL);

    $field_def = $this->createMock(FieldDefinitionInterface::class);
    $field_def->method('getLabel')->willReturn('Body');
    $field_def->method('getSetting')->willReturn('');

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->with('body')->willReturn(TRUE);
    $entity->method('get')->with('body')->willReturn($field_list);
    $entity->method('getFieldDefinition')->willReturnMap([
      ['body', $field_def],
      ['field_status', $field_def],
    ]);

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($entity);

    $form_state = $this->makeFormState('published', $form_object);
    $form_state->method('getFormObject')->willReturn($form_object);
    $form_state->method('getValue')->willReturnMap([
      ['field_status', NULL, [0 => ['value' => 'published']]],
      ['body', NULL, NULL],
    ]);
    $form_state->expects($this->once())->method('setErrorByName')
      ->with('body', $this->anything());
    $complete_form = [];

    $this->validator->validate($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validate
   */
  public function testNoErrorWhenRequiredFieldIsNotEmpty(): void {
    $conditions = [['field' => 'body', 'state' => 'published', 'visibility' => 'show', 'required' => TRUE]];
    $element = [
      '#state_machine_ui' => [
        'conditions' => $conditions,
        'state_field_name' => 'field_status',
      ],
    ];

    $this->resolver->method('getRequiredFields')->willReturn(['body']);

    $field_list = $this->createMock(FieldItemListInterface::class);
    $field_list->method('isEmpty')->willReturn(FALSE);
    $field_list->method('setValue')->willReturn(NULL);

    $field_def = $this->createMock(FieldDefinitionInterface::class);
    $field_def->method('getLabel')->willReturn('Body');
    $field_def->method('getSetting')->willReturn('');

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->with('body')->willReturn(TRUE);
    $entity->method('get')->with('body')->willReturn($field_list);
    $entity->method('getFieldDefinition')->willReturn($field_def);

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($entity);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);
    $form_state->method('getValue')->willReturnMap([
      ['field_status', NULL, [0 => ['value' => 'published']]],
      ['body', NULL, 'Some content'],
    ]);
    $form_state->expects($this->never())->method('setErrorByName');
    $complete_form = [];

    $this->validator->validate($element, $form_state, $complete_form);
  }

}
