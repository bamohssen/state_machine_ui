<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine_ui\Service\MermaidDiagramBuilder;
use Drupal\state_machine_ui\ValueObject\FieldType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for State Machine Workflow config entities.
 */
class WorkflowForm extends EntityForm {

  protected MermaidDiagramBuilder $diagramBuilder;
  protected EntityTypeBundleInfoInterface $bundleInfo;

  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->diagramBuilder = $container->get('state_machine_ui.mermaid_builder');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->setEntityTypeManager($container->get('entity_type.manager'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
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
      '#machine_name' => ['exists' => '\Drupal\state_machine_ui\Entity\WorkflowStateMachine::load'],
      '#disabled' => !$workflow->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional. Describe the purpose of this workflow.'),
      '#default_value' => $workflow->get('description') ?? '',
      '#rows' => 2,
    ];

    $group_options = [];
    $groups = $this->entityTypeManager->getStorage('workflow_group_config')->loadMultiple();
    foreach ($groups as $group) {
      $group_options[(string) $group->id()] = (string) $group->label();
    }

    $form['group'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow group'),
      '#description' => $this->t('Groups are linked to an entity type. <a href=":url">Manage groups</a>.', [
        ':url' => '/admin/config/workflow/state-machine/groups',
      ]),
      '#options' => $group_options,
      '#default_value' => $workflow->get('group') ?? '',
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select a group -'),
      '#ajax' => [
        'callback' => '::ajaxBindings',
        'wrapper' => 'bindings-wrapper',
      ],
      '#submit' => ['::refreshBindingsSubmit'],
      '#executes_submit_callback' => TRUE,
      '#limit_validation_errors' => [],
    ];

    $this->buildBindingsSection($form, $form_state);
    $this->buildFieldDefsSection($form, $form_state);
    $this->buildStatesSection($form, $form_state);
    $this->buildTransitionsSection($form, $form_state);
    $this->buildDiagramPreview($form, $form_state);

    return $form;
  }

  // =======================================================
  // Entity Bindings.
  // =======================================================

  private function buildBindingsSection(array &$form, FormStateInterface $form_state): void {
    $bindings = $this->getData($form_state, 'entity_bindings', $this->entity->get('entity_bindings') ?? []);
    $group_et = $this->resolveGroupEntityType($form_state);

    $form['bindings_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity bindings'),
      '#description' => $group_et
        ? $this->t('Select the bundle and the <em>state</em> field to bind this workflow to.')
        : $this->t('Select a workflow group first to configure bindings.'),
      '#open' => TRUE,
      '#prefix' => '<div id="bindings-wrapper">',
      '#suffix' => '</div>',
    ];

    if (!$group_et) {
      return;
    }

    $form['bindings_section']['bindings'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [$this->t('Bundle'), $this->t('Field'), $this->t('Operations')],
      '#empty' => $this->t('No bindings defined.'),
    ];

    $bundle_opts = $this->getBundleOptions($group_et);

    foreach ($bindings as $i => $b) {
      $bundle = $b['bundle'] ?? '';
      $field_opts = ($bundle !== '') ? $this->getStateFieldOptions($group_et, $bundle) : [];

      $form['bindings_section']['bindings'][$i] = [
        'bundle' => [
          '#type' => 'select',
          '#title' => $this->t('Bundle'),
          '#title_display' => 'invisible',
          '#options' => ['' => $this->t('- Select -')] + $bundle_opts,
          '#default_value' => $bundle,
          '#required' => TRUE,
          '#ajax' => ['callback' => '::ajaxBindings', 'wrapper' => 'bindings-wrapper'],
          '#submit' => ['::refreshBindingsSubmit'],
          '#executes_submit_callback' => TRUE,
          '#limit_validation_errors' => [],
        ],
        'field_name' => [
          '#type' => 'select',
          '#title' => $this->t('Field'),
          '#title_display' => 'invisible',
          '#options' => ['' => $this->t('- Select -')] + $field_opts,
          '#default_value' => $b['field_name'] ?? '',
          '#required' => TRUE,
          '#access' => !empty($field_opts),
        ],
        'ops' => $this->removeBtn("remove_binding_{$i}", '::removeBindingSubmit', 'bindings-wrapper', '::ajaxBindings'),
      ];

      if ($bundle !== '' && empty($field_opts)) {
        $form['bindings_section']['bindings'][$i]['field_name'] = [
          '#markup' => $this->t('No <em>state</em> field found on this bundle. <a href=":url">Add one first</a>.', [
            ':url' => "/admin/structure/types/manage/{$bundle}/fields",
          ]),
        ];
      }
    }

    $form['bindings_section']['add_binding'] = $this->ajaxBtn('Add binding', '::addBindingSubmit', 'bindings-wrapper', '::ajaxBindings');
  }

  public function ajaxBindings(array &$form, FormStateInterface $form_state): array {
    return $form['bindings_section'];
  }

  public function refreshBindingsSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $form_state->setRebuild();
  }

  public function addBindingSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $b = $form_state->get('entity_bindings');
    $b[] = ['entity_type' => $this->resolveGroupEntityType($form_state), 'bundle' => '', 'field_name' => ''];
    $form_state->set('entity_bindings', $b);
    $form_state->setRebuild();
  }

  public function removeBindingSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    if (($i = $this->triggerIndex($form_state, 'remove_binding_')) !== NULL) {
      $b = $form_state->get('entity_bindings');
      unset($b[$i]);
      $form_state->set('entity_bindings', array_values($b));
    }
    $form_state->setRebuild();
  }

  // =======================================================
  // Field Definitions.
  // =======================================================

  private function buildFieldDefsSection(array &$form, FormStateInterface $form_state): void {
    $defs = $this->getData($form_state, 'field_definitions', $this->entity->get('field_definitions') ?? []);

    $form['defs_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom field definitions'),
      '#description' => $this->t('Optional. Define metadata fields that each state can carry (e.g. <em>tags</em>, <em>category</em>). These can be used for metadata filtering in the widget.'),
      '#open' => !empty($defs),
    ];

    $form['defs_section']['defs'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [$this->t('Label'), $this->t('Key'), $this->t('Type'), $this->t('Description'), $this->t('Operations')],
      '#empty' => $this->t('No custom fields defined.'),
      '#prefix' => '<div id="defs-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($defs as $i => $d) {
      $form['defs_section']['defs'][$i] = [
        'label' => ['#type' => 'textfield', '#title' => $this->t('Label'), '#title_display' => 'invisible', '#default_value' => $d['label'] ?? '', '#size' => 20, '#required' => TRUE],
        'key' => ['#type' => 'textfield', '#title' => $this->t('Key'), '#title_display' => 'invisible', '#default_value' => $d['key'] ?? '', '#size' => 15, '#required' => TRUE, '#pattern' => '[a-z0-9_]+', '#attributes' => ['placeholder' => 'machine_name']],
        'type' => ['#type' => 'select', '#title' => $this->t('Type'), '#title_display' => 'invisible', '#options' => FieldType::options(), '#default_value' => $d['type'] ?? 'string', '#required' => TRUE],
        'description' => ['#type' => 'textfield', '#title' => $this->t('Description'), '#title_display' => 'invisible', '#default_value' => $d['description'] ?? '', '#size' => 25, '#attributes' => ['placeholder' => $this->t('Help text')]],
        'ops' => $this->removeBtn("remove_def_{$i}", '::removeDefSubmit', 'defs-wrapper', '::ajaxDefs'),
      ];
    }

    $form['defs_section']['add_def'] = $this->ajaxBtn('Add field', '::addDefSubmit', 'defs-wrapper', '::ajaxDefs');
  }

  public function ajaxDefs(array &$form, FormStateInterface $form_state): array {
    return $form['defs_section']['defs'];
  }

  public function addDefSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $d = $form_state->get('field_definitions');
    $d[] = ['key' => '', 'label' => '', 'type' => 'string', 'description' => ''];
    $form_state->set('field_definitions', $d);
    $form_state->setRebuild();
  }

  public function removeDefSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    if (($i = $this->triggerIndex($form_state, 'remove_def_')) !== NULL) {
      $d = $form_state->get('field_definitions');
      unset($d[$i]);
      $form_state->set('field_definitions', array_values($d));
    }
    $form_state->setRebuild();
  }

  // =======================================================
  // States.
  // =======================================================

  private function buildStatesSection(array &$form, FormStateInterface $form_state): void {
    $states = $this->getData($form_state, 'states', $this->entity->get('states') ?? []);
    $defs = $form_state->get('field_definitions') ?? [];

    $form['states_section'] = [
      '#type' => 'details',
      '#title' => $this->t('States'),
      '#description' => $this->t('Define all workflow states. Each needs a label and a unique machine name. Mark exactly one as <em>Initial</em>. <strong>Add states before transitions</strong> — the transition options come from here.'),
      '#open' => TRUE,
      '#prefix' => '<div id="states-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['states_section']['states_list'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($states as $i => $s) {
      $form['states_section']['states_list'][$i] = $this->buildStateSubform($i, $s, $defs);
    }

    $form['states_section']['add_state'] = $this->ajaxBtn('Add state', '::addStateSubmit', 'states-wrapper', '::ajaxStates');
  }

  private function buildStateSubform(int $i, array $s, array $defs): array {
    $el = [
      '#type' => 'details',
      '#title' => !empty($s['label']) ? $s['label'] : $this->t('State @n', ['@n' => $i + 1]),
      '#open' => empty($s['key']),
    ];

    $el['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $s['label'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('e.g. "Draft", "Under review", "Published".'),
    ];
    $el['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine name'),
      '#default_value' => $s['key'] ?? '',
      '#required' => TRUE,
      '#pattern' => '[a-z0-9_]+',
      '#description' => $this->t('Lowercase, underscores only: "draft", "under_review".'),
    ];
    $el['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $s['description'] ?? '',
      '#description' => $this->t('Optional internal notes.'),
    ];
    $el['is_initial'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Initial state'),
      '#default_value' => $s['is_initial'] ?? FALSE,
      '#description' => $this->t('Default state for new entities. Mark exactly one.'),
    ];

    if (!empty($defs)) {
      $el['fields'] = ['#type' => 'details', '#title' => $this->t('Metadata fields'), '#open' => TRUE];
      $fields = $s['fields'] ?? [];
      foreach ($defs as $d) {
        $fk = $d['key'] ?? '';
        if ($fk === '') {
          continue;
        }
        $el['fields'][$fk] = $this->buildDynamicField($d, $fields[$fk] ?? NULL);
      }
    }

    $el['remove'] = $this->removeBtn("remove_state_{$i}", '::removeStateSubmit', 'states-wrapper', '::ajaxStates', $this->t('Remove state'));

    return $el;
  }

  private function buildDynamicField(array $def, mixed $value): array {
    $type = FieldType::fromString($def['type'] ?? 'string');
    $label = $def['label'] ?? $def['key'];
    $desc = $def['description'] ?? '';
    return match ($type) {
      FieldType::List => ['#type' => 'textarea', '#title' => $label, '#default_value' => is_array($value) ? implode("\n", $value) : ($value ?? ''), '#description' => $desc ?: $this->t('One value per line.'), '#rows' => 3],
      FieldType::Boolean => ['#type' => 'checkbox', '#title' => $label, '#default_value' => (bool) ($value ?? FALSE), '#description' => $desc],
      FieldType::Number => ['#type' => 'number', '#title' => $label, '#default_value' => $value, '#description' => $desc, '#step' => 'any'],
      FieldType::String => ['#type' => 'textfield', '#title' => $label, '#default_value' => $value ?? '', '#description' => $desc],
    };
  }

  public function ajaxStates(array &$form, FormStateInterface $form_state): array {
    return $form['states_section'];
  }

  public function addStateSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $s = $form_state->get('states');
    $s[] = ['key' => '', 'label' => '', 'description' => '', 'is_initial' => FALSE, 'fields' => []];
    $form_state->set('states', $s);
    $form_state->setRebuild();
  }

  public function removeStateSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    if (($i = $this->triggerIndex($form_state, 'remove_state_')) !== NULL) {
      $s = $form_state->get('states');
      unset($s[$i]);
      $form_state->set('states', array_values($s));
    }
    $form_state->setRebuild();
  }

  // =======================================================
  // Transitions.
  // =======================================================

  private function buildTransitionsSection(array &$form, FormStateInterface $form_state): void {
    $transitions = $this->getData($form_state, 'transitions', $this->entity->get('transitions') ?? []);
    $state_opts = $this->buildStateSelectOptions($form_state->get('states') ?? []);

    $form['transitions_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Transitions'),
      '#description' => $this->t('Define allowed transitions. Each needs a label, machine name, one or more <em>From</em> states, and one <em>To</em> state.'),
      '#open' => TRUE,
    ];

    $form['transitions_section']['transitions'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [$this->t('Label'), $this->t('Key'), $this->t('From'), $this->t('To'), $this->t('Operations')],
      '#empty' => $this->t('No transitions defined. Add states first, then add transitions here.'),
      '#prefix' => '<div id="transitions-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($transitions as $i => $t) {
      $form['transitions_section']['transitions'][$i] = [
        'label' => ['#type' => 'textfield', '#title' => $this->t('Label'), '#title_display' => 'invisible', '#default_value' => $t['label'] ?? '', '#size' => 20, '#required' => TRUE],
        'key' => ['#type' => 'textfield', '#title' => $this->t('Key'), '#title_display' => 'invisible', '#default_value' => $t['key'] ?? '', '#size' => 15, '#required' => TRUE, '#pattern' => '[a-z0-9_]+', '#attributes' => ['placeholder' => 'machine_name']],
        'from' => ['#type' => 'checkboxes', '#title' => $this->t('From'), '#title_display' => 'invisible', '#options' => $state_opts, '#default_value' => $t['from'] ?? []],
        'to' => ['#type' => 'select', '#title' => $this->t('To'), '#title_display' => 'invisible', '#options' => ['' => $this->t('- Select -')] + $state_opts, '#default_value' => $t['to'] ?? '', '#required' => TRUE],
        'ops' => $this->removeBtn("remove_transition_{$i}", '::removeTransitionSubmit', 'transitions-wrapper', '::ajaxTransitions'),
      ];
    }

    $form['transitions_section']['add_transition'] = $this->ajaxBtn('Add transition', '::addTransitionSubmit', 'transitions-wrapper', '::ajaxTransitions');
  }

  public function ajaxTransitions(array &$form, FormStateInterface $form_state): array {
    return $form['transitions_section']['transitions'];
  }

  public function addTransitionSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    $t = $form_state->get('transitions');
    $t[] = ['key' => '', 'label' => '', 'from' => [], 'to' => ''];
    $form_state->set('transitions', $t);
    $form_state->setRebuild();
  }

  public function removeTransitionSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncAll($form_state);
    if (($i = $this->triggerIndex($form_state, 'remove_transition_')) !== NULL) {
      $t = $form_state->get('transitions');
      unset($t[$i]);
      $form_state->set('transitions', array_values($t));
    }
    $form_state->setRebuild();
  }

  // =======================================================
  // Diagram preview.
  // =======================================================

  private function buildDiagramPreview(array &$form, FormStateInterface $form_state): void {
    $states = $form_state->get('states') ?? [];
    $transitions = $form_state->get('transitions') ?? [];

    $has_data = FALSE;
    foreach ($states as $s) {
      if (!empty($s['key'])) {
        $has_data = TRUE;
        break;
      }
    }
    if (!$has_data) {
      return;
    }

    $diagram_code = $this->diagramBuilder->build($states, $transitions);

    $form['diagram_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Diagram preview'),
      '#open' => TRUE,
      '#weight' => 100,
    ];

    $form['diagram_section']['diagram'] = [
      '#type' => 'inline_template',
      '#template' => '<pre class="state-machine-mermaid-source" data-mermaid-source>{{ code }}</pre>',
      '#context' => ['code' => $diagram_code],
    ];

    $form['#attached']['library'][] = 'state_machine_ui/mermaid';
  }

  // =======================================================
  // Validation & Save.
  // =======================================================

  /**
   * {@inheritdoc}
   */
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
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\state_machine_ui\Entity\WorkflowStateMachine $workflow */
    $workflow = $this->entity;

    $this->syncAll($form_state);

    $entity_type = $this->resolveGroupEntityType($form_state);

    $bindings = $form_state->get('entity_bindings') ?? [];
    $clean_bindings = [];
    foreach ($bindings as $b) {
      if (!empty($b['bundle']) && !empty($b['field_name'])) {
        $clean_bindings[] = ['entity_type' => $entity_type, 'bundle' => $b['bundle'], 'field_name' => $b['field_name']];
      }
    }
    $workflow->set('entity_bindings', $clean_bindings);

    $defs = $form_state->get('field_definitions') ?? [];
    $clean_defs = [];
    foreach ($defs as $d) {
      if (!empty($d['key'])) {
        $clean_defs[] = ['key' => $d['key'], 'label' => $d['label'] ?: $d['key'], 'type' => $d['type'] ?? 'string', 'description' => $d['description'] ?? ''];
      }
    }
    $workflow->set('field_definitions', $clean_defs);

    $states = $form_state->get('states') ?? [];
    $clean_states = [];
    foreach ($states as $s) {
      if (!empty($s['key'])) {
        $clean_states[] = [
          'key' => $s['key'],
          'label' => $s['label'] ?: $s['key'],
          'description' => $s['description'] ?? '',
          'is_initial' => (bool) ($s['is_initial'] ?? FALSE),
          'fields' => $s['fields'] ?? [],
        ];
      }
    }
    $workflow->set('states', $clean_states);

    $transitions = $form_state->get('transitions') ?? [];
    $clean_transitions = [];
    foreach ($transitions as $t) {
      if (!empty($t['key'])) {
        $from = is_array($t['from'] ?? NULL) ? array_values(array_filter($t['from'])) : [];
        $clean_transitions[] = ['key' => $t['key'], 'label' => $t['label'] ?: $t['key'], 'from' => $from, 'to' => $t['to'] ?? ''];
      }
    }
    $workflow->set('transitions', $clean_transitions);

    $status = $workflow->save();
    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Created workflow %label.', ['%label' => $workflow->label()])
        : $this->t('Updated workflow %label.', ['%label' => $workflow->label()])
    );
    $form_state->setRedirectUrl($workflow->toUrl('collection'));
    return $status;
  }

  // =======================================================
  // Sync form input → form_state storage.
  // =======================================================

  private function syncAll(FormStateInterface $fs): void {
    $input = $fs->getUserInput();
    $entity_type = $this->resolveGroupEntityType($fs);

    $raw_bindings = $input['bindings'] ?? [];
    $bs = [];
    if (is_array($raw_bindings)) {
      foreach ($raw_bindings as $b) {
        if (is_array($b)) {
          $bs[] = ['entity_type' => $entity_type, 'bundle' => $b['bundle'] ?? '', 'field_name' => $b['field_name'] ?? ''];
        }
      }
    }
    $fs->set('entity_bindings', $bs);

    $raw_defs = $input['defs'] ?? [];
    $ds = [];
    if (is_array($raw_defs)) {
      foreach ($raw_defs as $d) {
        if (is_array($d)) {
          $ds[] = ['key' => $d['key'] ?? '', 'label' => $d['label'] ?? '', 'type' => $d['type'] ?? 'string', 'description' => $d['description'] ?? ''];
        }
      }
    }
    $fs->set('field_definitions', $ds);

    $raw_states = $input['states_list'] ?? [];
    $ss = [];
    if (is_array($raw_states)) {
      foreach ($raw_states as $v) {
        if (is_array($v) && isset($v['key'])) {
          $ss[] = [
            'key' => $v['key'] ?? '',
            'label' => $v['label'] ?? '',
            'description' => $v['description'] ?? '',
            'is_initial' => !empty($v['is_initial']),
            'fields' => $v['fields'] ?? [],
          ];
        }
      }
    }
    $fs->set('states', $ss);

    $raw_transitions = $input['transitions'] ?? [];
    $ts = [];
    if (is_array($raw_transitions)) {
      foreach ($raw_transitions as $t) {
        if (is_array($t)) {
          $from = is_array($t['from'] ?? NULL) ? array_values(array_filter($t['from'])) : [];
          $ts[] = ['key' => $t['key'] ?? '', 'label' => $t['label'] ?? '', 'from' => $from, 'to' => $t['to'] ?? ''];
        }
      }
    }
    $fs->set('transitions', $ts);
  }

  // =======================================================
  // Helpers.
  // =======================================================

  private function resolveGroupEntityType(FormStateInterface $form_state): string {
    $group_id = $form_state->getValue('group')
      ?? ($form_state->getUserInput()['group'] ?? '')
      ?: ($this->entity->get('group') ?? '');

    if ($group_id === '') {
      return '';
    }
    $group = $this->entityTypeManager->getStorage('workflow_group_config')->load($group_id);
    return $group ? (string) $group->get('entity_type') : '';
  }

  private function getData(FormStateInterface $fs, string $key, array $default): array {
    $data = $fs->get($key);
    if ($data === NULL) {
      $data = $default ?: [];
      $fs->set($key, $data);
    }
    return $data;
  }

  private function buildStateSelectOptions(array $states): array {
    $opts = [];
    foreach ($states as $s) {
      $k = $s['key'] ?? '';
      if ($k !== '') {
        $opts[$k] = $s['label'] ?: $k;
      }
    }
    return $opts;
  }

  private function triggerIndex(FormStateInterface $fs, string $prefix): ?int {
    $name = $fs->getTriggeringElement()['#name'] ?? '';
    return str_starts_with($name, $prefix) ? (int) substr($name, strlen($prefix)) : NULL;
  }

  private function getBundleOptions(string $et): array {
    $opts = [];
    foreach ($this->bundleInfo->getBundleInfo($et) as $id => $info) {
      $opts[$id] = (string) $info['label'];
    }
    asort($opts);
    return $opts;
  }

  private function getStateFieldOptions(string $et, string $bundle): array {
    try {
      $defs = \Drupal::service('entity_field.manager')->getFieldDefinitions($et, $bundle);
    }
    catch (\Exception) {
      return [];
    }
    $opts = [];
    foreach ($defs as $fn => $d) {
      if ($d->getType() === 'state') {
        $opts[$fn] = sprintf('%s (%s)', $d->getLabel(), $fn);
      }
    }
    return $opts;
  }

  private function ajaxBtn(string $label, string $submit, string $wrapper, string $cb): array {
    return [
      '#type' => 'submit',
      '#value' => $this->t($label),
      '#submit' => [$submit],
      '#ajax' => ['callback' => $cb, 'wrapper' => $wrapper],
      '#limit_validation_errors' => [],
      '#button_type' => 'small',
    ];
  }

  private function removeBtn(string $name, string $submit, string $wrapper, string $cb, $label = NULL): array {
    return [
      '#type' => 'submit',
      '#value' => $label ?? $this->t('Remove'),
      '#name' => $name,
      '#submit' => [$submit],
      '#ajax' => ['callback' => $cb, 'wrapper' => $wrapper],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button--small', 'button--danger']],
    ];
  }

}
