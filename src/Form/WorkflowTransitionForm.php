<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Entity\WorkflowMetadataSchema;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Entity\WorkflowTransition;

/**
 * Add/edit form for WorkflowTransition config entities.
 *
 * The form exposes the unprefixed transition key; the composite entity ID
 * is built from the parent workflow at save time, see
 * {@see WorkflowTransition::buildId()}. The parent workflow is taken from
 * the entity on edit, and from the route parameter on add.
 */
final class WorkflowTransitionForm extends ConfigEntityFormBase {

  /**
   * Parent workflow resolved once at form() and reused throughout.
   */
  private WorkflowStateMachine $workflow;

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\state_machine_ui\Entity\WorkflowTransition $transition */
    $transition = $this->entity;
    $this->workflow = $this->resolveWorkflow($transition);

    $state_options = $this->buildStateOptions();
    if ($state_options === []) {
      $form['no_states'] = [
        '#markup' => '<p>' . $this->t(
          'The parent workflow %workflow has no states yet. Add states on the workflow page before creating transitions.',
          ['%workflow' => $this->workflow->label()],
        ) . '</p>',
      ];
      return $form;
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $transition->label(),
      '#required' => TRUE,
    ];

    $form['key'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $transition->getKey(),
      '#machine_name' => [
        'exists' => [$this, 'transitionKeyExists'],
        'source' => ['label'],
        'label' => (string) $this->t('Machine name'),
      ],
      '#disabled' => !$transition->isNew(),
    ];

    $form['from'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('From states'),
      '#description' => $this->t('Allowed origin states. At least one must be selected.'),
      '#options' => $state_options,
      '#default_value' => $transition->getFromStates(),
      '#required' => TRUE,
    ];

    $form['to'] = [
      '#type' => 'select',
      '#title' => $this->t('To state'),
      '#description' => $this->t('Target state reached when the transition fires.'),
      '#options' => ['' => $this->t('- Select -')] + $state_options,
      '#default_value' => $transition->getToState(),
      '#required' => TRUE,
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $transition->getWeight(),
      '#delta' => 50,
    ];

    $this->buildMetadataSection($form, $transition);

    return $form;
  }

  /**
   * Adds a metadata section when the parent workflow declares a schema.
   *
   * One textarea per field defined on the workflow's transition metadata
   * schema. List-type fields are stored as one value per line; scalar
   * fields keep the raw input. Skipped silently when the workflow has no
   * transition_metadata_schema configured or the schema cannot be loaded.
   */
  private function buildMetadataSection(array &$form, WorkflowTransition $transition): void {
    $field_definitions = $this->getTransitionSchemaFieldDefinitions();
    if ($field_definitions === []) {
      return;
    }

    $form['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Transition metadata'),
      '#description' => $this->t('Custom values declared by the workflow %workflow transition metadata schema.', [
        '%workflow' => $this->workflow->label(),
      ]),
      '#tree' => TRUE,
      '#open' => FALSE,
    ];

    $stored_values = $transition->getFields();
    foreach ($field_definitions as $definition) {
      $key = (string) ($definition['key'] ?? '');
      if ($key === '') {
        continue;
      }
      $current = $stored_values[$key] ?? '';
      $is_list = ($definition['type'] ?? 'string') === 'list';
      $form['fields'][$key] = [
        '#type' => 'textarea',
        '#title' => (string) ($definition['label'] ?? $key),
        '#description' => (string) ($definition['description'] ?? ''),
        '#default_value' => $is_list && is_array($current)
          ? implode("\n", array_map('strval', $current))
          : (string) (is_scalar($current) ? $current : ''),
        '#rows' => $is_list ? 4 : 2,
      ];
    }
  }

  /**
   * Returns the field definitions of the workflow's transition schema.
   *
   * @return array<int, array{key: string, label?: string, type?: string, description?: string}>
   *   Schema field definitions, or empty array when no schema is attached.
   */
  private function getTransitionSchemaFieldDefinitions(): array {
    $schema_id = $this->workflow->getTransitionMetadataSchema();
    if ($schema_id === '') {
      return [];
    }
    $schema = $this->entityTypeManager
      ->getStorage(StateMachineUiConstants::ENTITY_SCHEMA)
      ->load($schema_id);
    return $schema instanceof WorkflowMetadataSchema ? $schema->getFieldDefinitions() : [];
  }

  /**
   * Machine name "exists" callback for the transition key field.
   *
   * Collisions are detected only within the current workflow; the same key
   * may legitimately be reused across workflows because the composite
   * entity ID disambiguates them.
   *
   * @param string $value
   *   The candidate machine name to test.
   *
   * @return bool
   *   TRUE when the key is already used by another transition of the same
   *   workflow.
   */
  public function transitionKeyExists(string $value): bool {
    $candidate_id = WorkflowTransition::buildId($this->workflow->id(), $value);
    if ($this->entity->id() === $candidate_id) {
      return FALSE;
    }
    return $this->entityTypeManager
      ->getStorage(StateMachineUiConstants::ENTITY_TRANSITION)
      ->load($candidate_id) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $from = array_values(array_filter((array) $form_state->getValue('from')));
    if ($from === []) {
      $form_state->setErrorByName('from', $this->t('Select at least one origin state.'));
    }

    $to = (string) $form_state->getValue('to');
    if ($to === '') {
      $form_state->setErrorByName('to', $this->t('Select a target state.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function copyFormValuesToEntity($entity, array $form, FormStateInterface $form_state): void {
    assert($entity instanceof WorkflowTransition);

    $key = (string) $form_state->getValue('key');
    $entity->set('key', $key);
    $entity->set('workflow', $this->workflow->id());
    $entity->set('id', WorkflowTransition::buildId($this->workflow->id(), $key));
    $entity->set('label', (string) $form_state->getValue('label'));
    $entity->set('from', array_values(array_filter((array) $form_state->getValue('from'))));
    $entity->set('to', (string) $form_state->getValue('to'));
    $entity->set('weight', (int) $form_state->getValue('weight'));
    $entity->set('fields', $this->extractFieldsFromInput($form_state->getValue('fields')));
  }

  /**
   * Cleans submitted metadata values into the storage shape used by getFields().
   *
   * - List-type schema fields receive an array of non-empty trimmed lines.
   * - Scalar fields receive a trimmed string.
   *
   * @param mixed $raw
   *   Raw `fields` value from the submitted form (array keyed by field key,
   *   or NULL when no metadata section was displayed).
   *
   * @return array<string, mixed>
   *   field_key => normalised value, omitting empty entries.
   */
  private function extractFieldsFromInput(mixed $raw): array {
    if (!is_array($raw)) {
      return [];
    }
    $definitions = $this->getTransitionSchemaFieldDefinitions();
    $types = [];
    foreach ($definitions as $definition) {
      $types[(string) ($definition['key'] ?? '')] = (string) ($definition['type'] ?? 'string');
    }

    $clean = [];
    foreach ($raw as $key => $value) {
      $key = (string) $key;
      if (!isset($types[$key]) || !is_string($value)) {
        continue;
      }
      if ($types[$key] === 'list') {
        $items = array_values(array_filter(
          array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: []),
          static fn(string $line): bool => $line !== ''
        ));
        if ($items !== []) {
          $clean[$key] = $items;
        }
        continue;
      }
      $trimmed = trim($value);
      if ($trimmed !== '') {
        $clean[$key] = $trimmed;
      }
    }
    return $clean;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function save(array $form, FormStateInterface $form_state): int {
    return $this->saveAndRedirect($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function entityNoun(): string {
    return (string) $this->t('transition');
  }

  /**
   * Resolves the parent workflow from the entity or the route parameter.
   *
   * Edit forms can rely on the transition's stored workflow ID; add forms
   * must fall back to the `workflow_state_machine` route parameter.
   *
   * @param \Drupal\state_machine_ui\Entity\WorkflowTransition $transition
   *   The current transition entity being edited or added.
   *
   * @return \Drupal\state_machine_ui\Entity\WorkflowStateMachine
   *   The resolved parent workflow.
   *
   * @throws \LogicException
   *   When the workflow cannot be resolved. Every routing entry declares
   *   the parameter, so reaching this means the form was invoked from an
   *   unsupported context.
   */
  private function resolveWorkflow(WorkflowTransition $transition): WorkflowStateMachine {
    $param = $this->getRouteMatch()->getParameter('workflow_state_machine');
    if ($param instanceof WorkflowStateMachine) {
      return $param;
    }

    $workflow_id = $transition->getWorkflowId() ?: (is_string($param) ? $param : '');
    if ($workflow_id !== '') {
      $workflow = $this->entityTypeManager
        ->getStorage(StateMachineUiConstants::ENTITY_WORKFLOW)
        ->load($workflow_id);
      if ($workflow instanceof WorkflowStateMachine) {
        return $workflow;
      }
    }

    throw new \LogicException('WorkflowTransitionForm requires a parent workflow_state_machine route parameter.');
  }

  /**
   * Builds the {state key => label} options for the From / To selectors.
   *
   * @return array<string, string>
   *   State keys mapped to their labels, in the order declared by the
   *   parent workflow.
   */
  private function buildStateOptions(): array {
    $options = [];
    foreach ($this->workflow->getStates() as $state) {
      $key = $state['key'] ?? '';
      if ($key !== '') {
        $options[$key] = $state['label'] ?? $key;
      }
    }
    return $options;
  }

}
