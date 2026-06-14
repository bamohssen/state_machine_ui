<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Constraint\MetadataValueConstraint;
use Drupal\state_machine_ui\Entity\WorkflowMetadataSchema;
use Drupal\state_machine_ui\Entity\WorkflowStateMachine;
use Drupal\state_machine_ui\Entity\WorkflowTransition;
use Drupal\state_machine_ui\Service\MermaidDiagramBuilder;
use Drupal\state_machine_ui\Service\MermaidDiagramBuilderInterface;
use Drupal\state_machine_ui\Service\WorkflowTransitionRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add/edit form for State Machine Workflow config entities.
 *
 * The form orchestrates AJAX callbacks, validation, save, and the
 * machine_name "exists" callbacks; rendering is delegated to
 * StatesTableBuilder and POST-input sync to WorkflowFormSyncTrait.
 *
 * Visible sections:
 *   1. Metadata schema selector (optional).
 *   2. States — draggable table with per-state metadata editing.
 *   3. Transitions pane — read-only summary plus a link to the dedicated
 *      transition management page.
 *   4. Diagram preview — Mermaid stateDiagram-v2 of the persisted shape.
 */
class WorkflowForm extends EntityForm {

  use WorkflowFormSyncTrait;

  public function __construct(
    protected MermaidDiagramBuilderInterface $diagramBuilder,
    protected StatesTableBuilder $statesBuilder,
    protected WorkflowTransitionRepositoryInterface $transitionRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(MermaidDiagramBuilderInterface::class),
      $container->get(StatesTableBuilder::class),
      $container->get(WorkflowTransitionRepositoryInterface::class),
    );
  }

  /**
   * Re-injects services after PHP unserialises the form object.
   */
  public function __wakeup(): void {
    $this->diagramBuilder = \Drupal::service(MermaidDiagramBuilderInterface::class);
    $this->statesBuilder = \Drupal::service(StatesTableBuilder::class);
    $this->transitionRepository = \Drupal::service(WorkflowTransitionRepositoryInterface::class);
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->moduleHandler = \Drupal::moduleHandler();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\state_machine_ui\Entity\WorkflowStateMachine $workflow */
    $workflow = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Workflow name'),
      '#description' => $this->t('A human-readable name, e.g. "Article publication" or "Patient pre-admission".'),
      '#maxlength' => 255,
      '#default_value' => $workflow->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $workflow->id(),
      '#machine_name' => [
        'exists' => WorkflowStateMachine::class . '::load',
      ],
      '#disabled' => !$workflow->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional. Describe the purpose of this workflow.'),
      '#default_value' => $workflow->getDescription(),
      '#rows' => 2,
    ];

    $form['group'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow group'),
      '#description' => $this->t('Groups are linked to an entity type. <a href=":url">Manage groups</a>.', [
        ':url' => Url::fromRoute('entity.workflow_group_config.collection')->toString(),
      ]),
      '#options' => $this->buildGroupOptions(),
      '#default_value' => $workflow->getGroup(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select a group -'),
    ];

    if ($form_state->get('state_metadata_schema') === NULL) {
      $form_state->set('state_metadata_schema', $workflow->getStateMetadataSchema());
    }
    if ($form_state->get('transition_metadata_schema') === NULL) {
      $form_state->set('transition_metadata_schema', $workflow->getTransitionMetadataSchema());
    }

    $schemas_link = Url::fromRoute('entity.workflow_metadata_schema.collection')->toString();
    $schema_options = $this->buildSchemaOptions();

    $form['state_metadata_schema'] = [
      '#type' => 'select',
      '#title' => $this->t('State metadata schema'),
      '#description' => $this->t(
        'Select a schema to define which custom fields each <strong>state</strong> can carry. <a href=":url">Manage schemas</a>.',
        [':url' => $schemas_link]
      ),
      '#options' => $schema_options,
      '#default_value' => $form_state->get('state_metadata_schema'),
      '#ajax' => [
        'callback' => '::ajaxStates',
        'wrapper' => 'states-wrapper',
        'event' => 'change',
      ],
    ];

    $form['transition_metadata_schema'] = [
      '#type' => 'select',
      '#title' => $this->t('Transition metadata schema'),
      '#description' => $this->t(
        'Select a schema to define which custom fields each <strong>transition</strong> can carry. <a href=":url">Manage schemas</a>.',
        [':url' => $schemas_link]
      ),
      '#options' => $schema_options,
      '#default_value' => $form_state->get('transition_metadata_schema'),
    ];

    $states = $this->seedFormStateData($form_state, 'states', $workflow->getStates());
    $states = $this->normalizeStateWeights($states);
    $form_state->set('states', $states);
    if ($form_state->get('default_state') === NULL) {
      $form_state->set('default_state', $workflow->getDefaultState());
    }

    $field_definitions = $this->getSchemaFieldDefs($form_state);
    $form['states_section'] = $this->statesBuilder->build(
      $form_state,
      $field_definitions,
      [$this, 'stateKeyExists'],
    );

    $this->buildTransitionsSection($form, $workflow);
    $this->buildDiagramPreview($form, $workflow, $form_state);

    return $form;
  }

  /**
   * Returns the refreshed states section.
   */
  public function ajaxStates(array &$form, FormStateInterface $form_state): array {
    return $form['states_section'];
  }

  /**
   * Checks key uniqueness within current states.
   */
  public function stateKeyExists(string $value, array $element, FormStateInterface $form_state): bool {
    $this->syncAll($form_state);
    $current_index = $element['#parents'][1] ?? NULL;
    foreach ($form_state->get('states') ?? [] as $index => $state) {
      if (($state['key'] ?? '') === $value && $index !== $current_index) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Appends a blank state row.
   */
  public function addStateSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $states = $form_state->get('states') ?? [];
    $max_weight = array_reduce(
      $states,
      static fn(int $carry, array $state): int => max($carry, (int) ($state['weight'] ?? 0)),
      0
    );
    $states[] = ['key' => '', 'label' => '', 'description' => '', 'weight' => $max_weight + 1, 'fields' => []];
    $form_state->set('states', $states);
    $form_state->setRebuild();
  }

  /**
   * Toggles the metadata sub-form for a given state row.
   */
  public function editStateMetadataSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $index = $this->triggerIndex($form_state, 'edit_state_metadata_');
    $current = $form_state->get('state_metadata_edit_index');
    $form_state->set('state_metadata_edit_index', ($current === $index) ? NULL : $index);
    $form_state->setRebuild();
  }

  /**
   * Writes metadata input into the target state, then closes the sub-form.
   */
  public function applyStateMetadataSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $index = $this->triggerIndex($form_state, 'apply_state_metadata_');

    if ($index === NULL) {
      $form_state->setRebuild();
      return;
    }

    $states = $form_state->get('states') ?? [];
    if (!isset($states[$index])) {
      $form_state->setRebuild();
      return;
    }

    // getUserInput() is intentional: the metadata sub-form is rebuilt via AJAX
    // before Drupal processes values, so getValue() returns stale data here.
    // Values are validated separately by MetadataValueConstraint.
    $raw_fields = $form_state->getUserInput()['state_metadata_edit']['fields'] ?? [];
    $states[$index]['fields'] = is_array($raw_fields) ? $raw_fields : [];
    $form_state->set('states', $states);
    $form_state->set('state_metadata_edit_index', NULL);
    $form_state->setRebuild();
  }

  /**
   * Removes the state identified by the triggering button.
   */
  public function removeStateSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $index = $this->triggerIndex($form_state, 'remove_state_');
    if ($index !== NULL) {
      $states = $form_state->get('states') ?? [];
      unset($states[$index]);
      $form_state->set('states', array_values($states));
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   *
   * Refuses the save when a removed state is still referenced by a transition.
   * Avoids producing transitions whose "from" or "to" target a non-existent
   * state.
   */
  #[\Override]
  public function validateForm(array &$form, FormStateInterface $form_state): array {
    parent::validateForm($form, $form_state);
    /** @var \Drupal\state_machine_ui\Entity\WorkflowStateMachine $workflow */
    $workflow = $this->entity;
    if ($workflow->isNew()) {
      return $form;
    }

    $this->syncAll($form_state);
    $next_keys = array_filter(array_column($form_state->get('states') ?? [], 'key'));
    $current_keys = array_filter(array_column($workflow->getStates(), 'key'));
    $removed = array_values(array_diff($current_keys, $next_keys));

    if ($removed === []) {
      return $form;
    }

    $blocking = $this->transitionRepository->findReferencingStates((string) $workflow->id(), $removed);
    if ($blocking === []) {
      return $form;
    }

    $labels = array_map(
      static fn(WorkflowTransition $transition): string => (string) $transition->label(),
      $blocking,
    );
    $form_state->setErrorByName('states_section', $this->t(
      'Cannot remove state(s) %states because they are still referenced by transition(s): %transitions. Edit or delete the transitions first.',
      ['%states' => implode(', ', $removed), '%transitions' => implode(', ', $labels)],
    ));

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function copyFormValuesToEntity($entity, array $form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    foreach (['label', 'id', 'description', 'group'] as $key) {
      if (isset($values[$key])) {
        $entity->set($key, $values[$key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\state_machine_ui\Entity\WorkflowStateMachine $workflow */
    $workflow = $this->entity;

    $this->syncAll($form_state);

    $workflow->set('state_metadata_schema', (string) ($form_state->get('state_metadata_schema') ?? ''));
    $workflow->set('transition_metadata_schema', (string) ($form_state->get('transition_metadata_schema') ?? ''));

    $states = $form_state->get('states') ?? [];
    usort($states, static fn(array $a, array $b): int => ((int) ($a['weight'] ?? 0)) <=> ((int) ($b['weight'] ?? 0)));
    $valid_keys = [];
    $clean_states = [];
    $weight = 0;

    foreach ($states as $state) {
      if (empty($state['key'])) {
        continue;
      }
      $clean_states[] = [
        'key' => $state['key'],
        'label' => $state['label'] ?: $state['key'],
        'description' => $state['description'] ?? '',
        'weight' => $weight++,
        'fields' => is_array($state['fields'] ?? NULL) ? $state['fields'] : [],
      ];
      $valid_keys[] = $state['key'];
    }

    $workflow->set('states', $clean_states);

    $default_state = (string) ($form_state->get('default_state') ?? '');
    if ($default_state === '' || !in_array($default_state, $valid_keys, TRUE)) {
      $default_state = $valid_keys[0] ?? '';
    }
    $workflow->set('default_state', $default_state);

    $status = $workflow->save();
    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Created workflow %label.', ['%label' => $workflow->label()])
        : $this->t('Updated workflow %label.', ['%label' => $workflow->label()])
    );
    $form_state->setRedirectUrl($workflow->toUrl('collection'));

    return $status;
  }

  /**
   * Element validator: enforces MetadataValueConstraint on textarea list values.
   */
  public static function validateMachineNameValue(array &$element, FormStateInterface $form_state): void {
    $raw = $element['#value'] ?? '';
    if (!is_string($raw) || $raw === '') {
      return;
    }

    $is_list = ($element['#type'] ?? '') === 'textarea';
    $values = $is_list
      ? array_filter(
          array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: []),
          static fn(string $line): bool => $line !== ''
        )
      : [trim($raw)];

    $invalid = array_filter(
      $values,
      static fn(string $value): bool => !MetadataValueConstraint::isValid($value)
    );

    if (!empty($invalid)) {
      $form_state->setError($element, t(
        'The field %label only accepts lowercase letters, digits and underscores. Invalid: %values',
        ['%label' => $element['#title'] ?? '', '%values' => implode(', ', $invalid)]
      ));
    }
  }

  /**
   * Builds the transitions navigation pane (count + link to dedicated page).
   */
  private function buildTransitionsSection(array &$form, WorkflowStateMachine $workflow): void {
    $form['transitions_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Transitions'),
      '#open' => TRUE,
      '#weight' => 50,
    ];

    if ($workflow->isNew()) {
      $form['transitions_section']['notice'] = [
        '#markup' => '<p>' . $this->t('Save the workflow first, then manage its transitions on the dedicated page.') . '</p>',
      ];
      return;
    }

    $transitions = $this->transitionRepository->loadByWorkflow((string) $workflow->id());
    $count = count($transitions);

    $form['transitions_section']['summary'] = [
      '#markup' => '<p>' . $this->formatPlural(
        $count,
        '@count transition currently defined.',
        '@count transitions currently defined.',
      ) . '</p>',
    ];

    $form['transitions_section']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage transitions'),
      '#url' => Url::fromRoute(
        'entity.workflow_transition.collection',
        ['workflow_state_machine' => $workflow->id()],
      ),
      '#attributes' => ['class' => ['button']],
    ];
  }

  /**
   * Appends a Mermaid diagram preview when at least one state exists.
   *
   * The preview reads transitions from storage, not form_state, so unsaved
   * state edits show up only after the workflow has been saved.
   */
  private function buildDiagramPreview(array &$form, WorkflowStateMachine $workflow, FormStateInterface $form_state): void {
    $state_keys = array_filter(array_column($form_state->get('states') ?? [], 'key'));
    if (empty($state_keys)) {
      return;
    }

    $transitions = $this->mapTransitionsForDiagram($workflow);

    $form['diagram_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Diagram preview'),
      '#open' => TRUE,
      '#weight' => 100,
    ];

    $form['diagram_section']['diagram'] = [
      '#type' => 'inline_template',
      '#template' => MermaidDiagramBuilder::INLINE_TEMPLATE,
      '#context' => [
        'diagram' => $this->diagramBuilder->build(
          $form_state->get('states') ?? [],
          $transitions,
        ),
      ],
    ];

    $form['#attached']['library'][] = 'state_machine_ui/mermaid';
  }

  /**
   * Maps stored transitions to the structure expected by the diagram builder.
   *
   * @return array<int, array{label: string, from: string[], to: string}>
   *   Persisted transitions normalised to the Mermaid builder's input shape.
   */
  private function mapTransitionsForDiagram(WorkflowStateMachine $workflow): array {
    if ($workflow->isNew()) {
      return [];
    }
    return array_map(
      static fn($transition): array => [
        'label' => (string) $transition->label(),
        'from' => $transition->getFromStates(),
        'to' => $transition->getToState(),
      ],
      array_values($this->transitionRepository->loadByWorkflow((string) $workflow->id())),
    );
  }

  /**
   * Sorts states by weight, then reassigns contiguous integer weights.
   */
  private function normalizeStateWeights(array $states): array {
    usort(
      $states,
      static fn(array $a, array $b): int =>
        ((int) ($a['weight'] ?? PHP_INT_MAX)) <=> ((int) ($b['weight'] ?? PHP_INT_MAX))
    );
    foreach (array_keys($states) as $index) {
      $states[$index]['weight'] = $index;
    }
    return $states;
  }

  /**
   * Returns field definitions of the state metadata schema currently selected.
   *
   * Reads getUserInput() first so AJAX rebuilds triggered by a schema change
   * already see the newly picked schema.
   */
  private function getSchemaFieldDefs(FormStateInterface $form_state): array {
    $input = $form_state->getUserInput();
    $schema_id = array_key_exists('state_metadata_schema', $input)
      ? (string) $input['state_metadata_schema']
      : (string) ($form_state->get('state_metadata_schema') ?? '');

    if ($schema_id === '') {
      return [];
    }

    $schema = $this->entityTypeManager->getStorage(StateMachineUiConstants::ENTITY_SCHEMA)->load($schema_id);
    if (!$schema instanceof WorkflowMetadataSchema) {
      return [];
    }

    return $schema->getFieldDefinitions();
  }

  /**
   * Builds the options array for the workflow group select element.
   */
  private function buildGroupOptions(): array {
    $options = [];
    foreach ($this->entityTypeManager->getStorage(StateMachineUiConstants::ENTITY_GROUP)->loadMultiple() as $group) {
      $options[(string) $group->id()] = (string) $group->label();
    }
    return $options;
  }

  /**
   * Builds the options array for the metadata schema select element.
   */
  private function buildSchemaOptions(): array {
    $options = ['' => $this->t('- No metadata schema -')];
    foreach ($this->entityTypeManager->getStorage(StateMachineUiConstants::ENTITY_SCHEMA)->loadMultiple() as $schema) {
      $options[(string) $schema->id()] = (string) $schema->label();
    }
    return $options;
  }

}
