<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\state_machine_ui\Form\ConditionsTableBuilder;
use Drupal\state_machine_ui\Service\MermaidDiagramBuilder;
use Drupal\state_machine_ui\Service\MermaidLibraryLocator;
use Drupal\state_machine_ui\Service\MetadataFilterInterface;
use Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for state fields with conditional rules and metadata filtering.
 *
 * @FieldWidget(
 *   id = "state_field_rules",
 *   label = @Translation("State with rules"),
 *   field_types = {
 *     "state"
 *   }
 * )
 */
class StateFieldRulesWidget extends WidgetBase {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected readonly ConditionsTableBuilder $conditionsTableBuilder,
    protected readonly WorkflowMetadataReaderInterface $metadataReader,
    protected readonly MetadataFilterInterface $metadataFilter,
    protected readonly MermaidDiagramBuilder $diagramBuilder,
    protected readonly MermaidLibraryLocator $libraryLocator,
    protected readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('state_machine_ui.conditions_table_builder'),
      $container->get('state_machine_ui.metadata_reader'),
      $container->get('state_machine_ui.metadata_filter'),
      $container->get('state_machine_ui.mermaid_builder'),
      $container->get('state_machine_ui.mermaid_locator'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'no_transition_behavior' => 'hide',
      'no_transition_message' => 'No transitions available.',
      'conditions' => [],
      'state_filters' => [],
      'transition_filters' => [],
      'filter_logic' => 'and',
      'show_workflow_diagram' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * Gets a setting as array, returning [] if not an array.
   */
  private function getArraySetting(string $key): array {
    $value = $this->getSetting($key);
    return is_array($value) ? $value : [];
  }

  /**
   * Gets a setting as string, returning '' if not a string.
   */
  private function getStringSetting(string $key): string {
    $value = $this->getSetting($key);
    return is_string($value) ? $value : '';
  }

  // =======================================================
  // Settings form.
  // =======================================================

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['no_transition_behavior'] = [
      '#type' => 'radios',
      '#title' => $this->t('When no transition is available'),
      '#options' => [
        'hide' => $this->t('Hide the select, show current state only'),
        'message' => $this->t('Show a custom message'),
      ],
      '#default_value' => $this->getStringSetting('no_transition_behavior') ?: 'hide',
    ];

    $element['no_transition_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No transition message'),
      '#default_value' => $this->getStringSetting('no_transition_message'),
      '#states' => [
        'visible' => [
          ':input[name*="no_transition_behavior"]' => ['value' => 'message'],
        ],
      ],
    ];

    $element['show_workflow_diagram'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show workflow diagram'),
      '#description' => $this->libraryLocator->isInstalled()
        ? $this->t('Display a Mermaid.js diagram of possible transitions from the current state.')
        : $this->t('Mermaid.js library not found. Install it in <code>libraries/mermaid/dist/</code>.'),
      '#default_value' => (bool) $this->getSetting('show_workflow_diagram'),
      '#disabled' => !$this->libraryLocator->isInstalled(),
    ];

    $element['conditions'] = $this->conditionsTableBuilder->build(
      $this->getSetting('conditions'),
      $this->fieldDefinition,
      $form_state,
    );

    $this->buildMetadataFilterSettings($element);

    return $element;
  }

  /**
   * Builds the metadata filter settings section.
   */
  protected function buildMetadataFilterSettings(array &$element): void {
    $workflow_id = $this->fieldDefinition->getSetting('workflow');
    if (empty($workflow_id)) {
      return;
    }

    $state_metadata = $this->metadataReader->getStateMetadata($workflow_id);
    $transition_metadata = $this->metadataReader->getTransitionMetadata($workflow_id);

    if (empty($state_metadata) && empty($transition_metadata)) {
      return;
    }

    $current_state_filters = $this->getArraySetting('state_filters');
    $current_transition_filters = $this->getArraySetting('transition_filters');

    $element['metadata_filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Metadata filtering'),
      '#description' => $this->t('Filter which states and transitions are shown based on workflow metadata.'),
      '#open' => !empty($current_state_filters) || !empty($current_transition_filters),
    ];

    if (!empty($state_metadata)) {
      $element['metadata_filters']['state_filters'] = [
        '#type' => 'details',
        '#title' => $this->t('State metadata filters'),
        '#open' => TRUE,
      ];
      foreach ($state_metadata as $key => $values) {
        $defaults = $current_state_filters[$key] ?? [];
        $element['metadata_filters']['state_filters'][$key] = [
          '#type' => 'checkboxes',
          '#title' => $key,
          '#options' => array_combine($values, $values),
          '#default_value' => is_array($defaults) ? $defaults : [],
        ];
      }
    }

    if (!empty($transition_metadata)) {
      $element['metadata_filters']['transition_filters'] = [
        '#type' => 'details',
        '#title' => $this->t('Transition metadata filters'),
        '#open' => TRUE,
      ];
      foreach ($transition_metadata as $key => $values) {
        $defaults = $current_transition_filters[$key] ?? [];
        $element['metadata_filters']['transition_filters'][$key] = [
          '#type' => 'checkboxes',
          '#title' => $key,
          '#options' => array_combine($values, $values),
          '#default_value' => is_array($defaults) ? $defaults : [],
        ];
      }
    }

    $element['metadata_filters']['filter_logic'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter logic between keys'),
      '#options' => [
        'and' => $this->t('AND — must match all keys'),
        'or' => $this->t('OR — must match at least one key'),
      ],
      '#default_value' => $this->getStringSetting('filter_logic') ?: 'and',
    ];
  }

  // =======================================================
  // Settings summary.
  // =======================================================

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $behavior = $this->getStringSetting('no_transition_behavior');
    if ($behavior === 'message') {
      $summary[] = $this->t('No transition: show message "@msg"', [
        '@msg' => $this->getStringSetting('no_transition_message'),
      ]);
    }
    else {
      $summary[] = $this->t('No transition: hide select');
    }

    $conditions = $this->getArraySetting('conditions');
    if (!empty($conditions)) {
      $summary[] = $this->t('@count conditional rule(s)', [
        '@count' => count($conditions),
      ]);
    }

    $state_filters = $this->getActiveFilters($this->getArraySetting('state_filters'));
    $transition_filters = $this->getActiveFilters($this->getArraySetting('transition_filters'));

    if (!empty($state_filters)) {
      $summary[] = $this->t('State filters: @keys', ['@keys' => implode(', ', array_keys($state_filters))]);
    }
    if (!empty($transition_filters)) {
      $summary[] = $this->t('Transition filters: @keys', ['@keys' => implode(', ', array_keys($transition_filters))]);
    }
    if (!empty($state_filters) || !empty($transition_filters)) {
      $logic = $this->getStringSetting('filter_logic') === 'or' ? 'OR' : 'AND';
      $summary[] = $this->t('Filter logic: @logic', ['@logic' => $logic]);
    }

    if ($this->getSetting('show_workflow_diagram')) {
      $summary[] = $this->libraryLocator->isInstalled()
        ? $this->t('Diagram: enabled')
        : $this->t('Diagram: enabled (library missing)');
    }

    return $summary;
  }

  // =======================================================
  // Form element.
  // =======================================================

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $items[$delta];
    $workflow = $state_item->getWorkflow();

    if (!$workflow) {
      return $element;
    }

    $current_id = $state_item->getId() ?? '';
    $current_label = $state_item->getLabel() ?? '';

    // On new entities the state may not be set yet.
    if ($current_id === '') {
      $states = $workflow->getStates();
      if (!empty($states)) {
        $first = reset($states);
        $current_id = $first->getId();
        $current_label = $first->getLabel();
      }
      else {
        return $element;
      }
    }

    $element['current_state'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="state-machine-current-state"><strong>{{ label }}:</strong> {{ state }}</div>',
      '#context' => [
        'label' => $this->t('Current state'),
        'state' => $current_label,
      ],
    ];

    $allowed_transitions = $state_item->getTransitions();
    $allowed_transitions = $this->applyMetadataFilters($workflow->getId(), $allowed_transitions);
    $target_options = $this->buildTargetStateOptions($allowed_transitions, $current_id, $current_label);

    if (count($target_options) <= 1) {
      return $this->buildNoTransitionElement($element, $current_id);
    }

    $element['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Transition to'),
      '#options' => $target_options,
      '#default_value' => $current_id,
    ];

    // Partial diagram: only transitions from the current state.
    $this->buildDiagramElement($element, $workflow, $current_id, $allowed_transitions);

    return $element;
  }

  /**
   * Builds deduplicated target state options from allowed transitions.
   */
  protected function buildTargetStateOptions(array $transitions, string $current_id, string $current_label): array {
    $options = [$current_id => $current_label];
    foreach ($transitions as $transition) {
      $to_state = $transition->getToState();
      $to_id = $to_state->getId();
      if (!isset($options[$to_id])) {
        $options[$to_id] = $to_state->getLabel();
      }
    }
    return $options;
  }

  /**
   * Builds the widget element when no transitions are available.
   */
  protected function buildNoTransitionElement(array $element, string $current_id): array {
    $element['value'] = [
      '#type' => 'hidden',
      '#value' => $current_id,
    ];
    $behavior = $this->getStringSetting('no_transition_behavior');
    if ($behavior === 'message') {
      $message = $this->getStringSetting('no_transition_message') ?: $this->t('No transitions available.');
      $element['no_transition'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="state-machine-no-transition messages messages--warning">{{ message }}</div>',
        '#context' => ['message' => $message],
      ];
    }
    return $element;
  }

  /**
   * Adds a partial Mermaid diagram (current state transitions only).
   */
  protected function buildDiagramElement(array &$element, $workflow, string $current_id, array $allowed_transitions): void {
    if (!$this->getSetting('show_workflow_diagram')) {
      return;
    }
    if (!$this->currentUser->hasPermission('view state machine diagram')) {
      return;
    }
    if (!$this->libraryLocator->isInstalled()) {
      return;
    }

    $mermaid_code = $this->diagramBuilder->buildCurrentStateTransitions($workflow, $current_id, $allowed_transitions);

    $element['diagram'] = [
      '#type' => 'details',
      '#title' => $this->t('Possible transitions'),
      '#open' => FALSE,
    ];
    $element['diagram']['chart'] = [
      '#type' => 'inline_template',
      '#template' => '<pre class="state-machine-mermaid-source" data-mermaid-source>{{ code }}</pre>',
      '#context' => ['code' => $mermaid_code],
    ];
    $element['diagram']['#attached']['library'][] = 'state_machine_ui/mermaid';
  }

  // =======================================================
  // Metadata filtering.
  // =======================================================

  /**
   * Applies metadata filters to allowed transitions.
   */
  protected function applyMetadataFilters(string $workflow_id, array $transitions): array {
    $state_filters = $this->getActiveFilters($this->getArraySetting('state_filters'));
    $transition_filters = $this->getActiveFilters($this->getArraySetting('transition_filters'));

    if (empty($state_filters) && empty($transition_filters)) {
      return $transitions;
    }

    $logic = $this->getStringSetting('filter_logic') ?: 'and';

    if (!empty($state_filters)) {
      $all_target_ids = [];
      foreach ($transitions as $t) {
        $all_target_ids[] = $t->getToState()->getId();
      }
      $allowed_states = $this->metadataFilter->filterStates(
        $workflow_id,
        array_unique($all_target_ids),
        $state_filters,
        $logic,
      );
      $transitions = array_filter(
        $transitions,
        static fn($t): bool => in_array($t->getToState()->getId(), $allowed_states, TRUE),
      );
    }

    if (!empty($transition_filters)) {
      $allowed_ids = $this->metadataFilter->filterTransitions(
        $workflow_id,
        array_keys($transitions),
        $transition_filters,
        $logic,
      );
      $transitions = array_filter(
        $transitions,
        static fn($t, $id): bool => in_array($id, $allowed_ids, TRUE),
        ARRAY_FILTER_USE_BOTH,
      );
    }

    return $transitions;
  }

  /**
   * Cleans checkbox values from widget settings.
   */
  protected function getActiveFilters(array $raw_filters): array {
    $active = [];
    foreach ($raw_filters as $key => $values) {
      if (!is_array($values)) {
        continue;
      }
      $checked = array_values(array_filter($values, static fn($v): bool => $v !== 0 && $v !== '0' && $v !== ''));
      if (!empty($checked)) {
        $active[$key] = $checked;
      }
    }
    return $active;
  }

}
