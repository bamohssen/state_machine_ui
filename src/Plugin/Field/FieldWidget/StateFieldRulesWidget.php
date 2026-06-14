<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Plugin\Field\FieldWidget;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine_ui\Constant\CommentMode;
use Drupal\state_machine_ui\Constant\FilterLogic;
use Drupal\state_machine_ui\Constant\OptionLabelSource;
use Drupal\state_machine_ui\Constant\Visibility;
use Drupal\state_machine_ui\Form\ConditionsTableBuilder;
use Drupal\state_machine_ui\Service\DefaultStateResolverInterface;
use Drupal\state_machine_ui\Service\MermaidDiagramBuilder;
use Drupal\state_machine_ui\Service\MermaidDiagramBuilderInterface;
use Drupal\state_machine_ui\Service\MermaidLibraryLocatorInterface;
use Drupal\state_machine_ui\Service\MetadataFilterInterface;
use Drupal\state_machine_ui\Service\TransitionAccessCheckerInterface;
use Drupal\state_machine_ui\Service\TransitionHistoryProviderInterface;
use Drupal\state_machine_ui\Service\TransitionOptionsBuilderInterface;
use Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for state fields with conditional rules and metadata filtering.
 */
#[FieldWidget(
  id: 'state_field_rules',
  label: new TranslatableMarkup('State with rules'),
  field_types: ['state'],
)]
class StateFieldRulesWidget extends WidgetBase {

  /**
   * Constructs a StateFieldRulesWidget instance.
   *
   * @param string $plugin_id
   *   The plugin ID for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\state_machine_ui\Form\ConditionsTableBuilder $conditionsTableBuilder
   *   The conditions table builder.
   * @param \Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface $metadataReader
   *   The workflow metadata reader.
   * @param \Drupal\state_machine_ui\Service\MetadataFilterInterface $metadataFilter
   *   The metadata filter service.
   * @param \Drupal\state_machine_ui\Service\MermaidDiagramBuilderInterface $diagramBuilder
   *   The Mermaid diagram builder.
   * @param \Drupal\state_machine_ui\Service\MermaidLibraryLocatorInterface $libraryLocator
   *   The Mermaid library locator.
   * @param \Drupal\state_machine_ui\Service\DefaultStateResolverInterface $defaultStateResolver
   *   The default state resolver.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user proxy.
   * @param \Drupal\state_machine_ui\Service\TransitionAccessCheckerInterface $transitionAccessChecker
   *   Filters transitions by per-transition permission.
   * @param \Drupal\state_machine_ui\Service\TransitionOptionsBuilderInterface $optionsBuilder
   *   Builds the deduplicated option list for the selector.
   * @param \Drupal\state_machine_ui\Service\TransitionHistoryProviderInterface $historyProvider
   *   Provides past transition entries from the entity revisions.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Formats timestamps for the history table.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected readonly ConditionsTableBuilder $conditionsTableBuilder,
    protected readonly WorkflowMetadataReaderInterface $metadataReader,
    protected readonly MetadataFilterInterface $metadataFilter,
    protected readonly MermaidDiagramBuilderInterface $diagramBuilder,
    protected readonly MermaidLibraryLocatorInterface $libraryLocator,
    protected readonly DefaultStateResolverInterface $defaultStateResolver,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly TransitionAccessCheckerInterface $transitionAccessChecker,
    protected readonly TransitionOptionsBuilderInterface $optionsBuilder,
    protected readonly TransitionHistoryProviderInterface $historyProvider,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
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
      $container->get('state_machine_ui.default_state_resolver'),
      $container->get('current_user'),
      $container->get(TransitionAccessCheckerInterface::class),
      $container->get(TransitionOptionsBuilderInterface::class),
      $container->get(TransitionHistoryProviderInterface::class),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function defaultSettings(): array {
    return [
      'is_required' => TRUE,
      'select_label' => '',
      'show_empty_option' => FALSE,
      'empty_option_label' => '',
      'option_label_source' => OptionLabelSource::State->value,
      'comment_mode' => CommentMode::Disabled->value,
      'comment_label' => '',
      'check_transition_access' => FALSE,
      'no_transition_behavior' => 'hide',
      'no_transition_message' => '',
      'conditions' => [],
      'state_filters' => [],
      'transition_filters' => [],
      'filter_logic' => FilterLogic::And->value,
      'show_workflow_diagram' => FALSE,
      'show_history' => FALSE,
      'history_limit' => 5,
    ] + parent::defaultSettings();
  }

  /**
   * Gets a widget setting cast to array, returning [] if not an array.
   *
   * @param string $key
   *   The setting key.
   *
   * @return array
   *   The setting value as array.
   */
  private function getArraySetting(string $key): array {
    $value = $this->getSetting($key);
    return is_array($value) ? $value : [];
  }

  /**
   * Gets a widget setting cast to string, returning '' if not a string.
   *
   * @param string $key
   *   The setting key.
   *
   * @return string
   *   The setting value as string.
   */
  private function getStringSetting(string $key): string {
    $value = $this->getSetting($key);
    return is_string($value) ? $value : '';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['is_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is required'),
      '#description' => $this->t('When unchecked, the state select is not marked as required even if the field is required at the storage level.'),
      '#default_value' => (bool) $this->getSetting('is_required'),
    ];

    $element['option_label_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Option labels'),
      '#options' => array_map([$this, 't'], OptionLabelSource::options()),
      '#default_value' => $this->getStringSetting('option_label_source') ?: OptionLabelSource::State->value,
      '#description' => $this->t('Use the target state label (default) or the transition label as the visible option.'),
    ];

    $element['select_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom select label'),
      '#default_value' => $this->getStringSetting('select_label'),
      '#description' => $this->t('Leave empty to use "Transition to".'),
    ];

    $element['show_empty_option'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show an empty option as default'),
      '#description' => $this->t('When checked, the selector starts unselected and the user must explicitly choose a transition.'),
      '#default_value' => (bool) $this->getSetting('show_empty_option'),
    ];

    $element['empty_option_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty option label'),
      '#default_value' => $this->getStringSetting('empty_option_label'),
      '#states' => [
        'visible' => [':input[name*="show_empty_option"]' => ['checked' => TRUE]],
      ],
      '#description' => $this->t('Leave empty to use "- Choose -".'),
    ];

    $element['comment_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Transition comment'),
      '#options' => array_map([$this, 't'], CommentMode::options()),
      '#default_value' => $this->getStringSetting('comment_mode') ?: CommentMode::Disabled->value,
      '#description' => $this->t('Show a comment field next to the selector. Stored as the revision log message when the entity is revisionable.'),
    ];

    $element['comment_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Comment field label'),
      '#default_value' => $this->getStringSetting('comment_label'),
      '#states' => [
        'invisible' => [':input[name*="comment_mode"]' => ['value' => CommentMode::Disabled->value]],
      ],
      '#description' => $this->t('Leave empty to use "Transition comment".'),
    ];

    $element['check_transition_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check per-transition permission'),
      '#description' => $this->t('Only show transitions the current user has the matching "use … transition" permission for.'),
      '#default_value' => (bool) $this->getSetting('check_transition_access'),
    ];

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

    $element['show_history'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show transition history'),
      '#description' => $this->t('Display a table of past state changes below the selector. Requires the entity to be revisionable.'),
      '#default_value' => (bool) $this->getSetting('show_history'),
    ];

    $element['history_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('History entries to display'),
      '#min' => -1,
      '#max' => 100,
      '#step' => 1,
      '#default_value' => (int) $this->getSetting('history_limit') ?: 5,
      '#description' => $this->t('Use -1 to show every recorded transition.'),
      '#states' => [
        'visible' => [':input[name*="show_history"]' => ['checked' => TRUE]],
      ],
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
   * Builds the metadata filter settings section inside the settings form.
   *
   * Renders checkboxes for each metadata key/value pair found on the workflow,
   * plus an inter-key logic selector. Skipped entirely if no metadata is defined.
   *
   * @param array $element
   *   The settings form element array, modified in place.
   */
  protected function buildMetadataFilterSettings(array &$element): void {
    $workflow_id = $this->fieldDefinition->getSetting('workflow');
    if (empty($workflow_id)) {
      return;
    }

    if (!\Drupal::service('plugin.manager.workflow')->hasDefinition($workflow_id)) {
      $element['workflow_missing'] = [
        '#type' => 'item',
        '#markup' => '<em>' . $this->t(
          'Workflow %id is referenced by this field but no longer exists. Update the field settings.',
          ['%id' => $workflow_id],
        ) . '</em>',
      ];
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
      '#description' => $this->t('<p><strong>Purpose:</strong> narrow the selector down to the states and transitions that carry specific metadata. For example, only show states tagged "editorial" or transitions marked "urgent".</p><p><strong>Behavior:</strong></p><ul><li>Without any filter, every state and transition is shown.</li><li>Tick the metadata values you want to keep.</li><li>Inside one metadata key, every ticked value must be present.</li><li>Between metadata keys, choose AND or OR with the setting below.</li><li>This filter only changes the display, not the workflow permissions.</li></ul>'),
      '#open' => !empty($current_state_filters) || !empty($current_transition_filters),
    ];

    if (!empty($state_metadata)) {
      $element['metadata_filters']['state_filters'] = [
        '#type' => 'details',
        '#title' => $this->t('State metadata filters'),
        '#open' => TRUE,
      ];
      foreach ($state_metadata as $metadata_key => $values) {
        $defaults = $current_state_filters[$metadata_key] ?? [];
        $element['metadata_filters']['state_filters'][$metadata_key] = [
          '#type' => 'checkboxes',
          '#title' => $metadata_key,
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
      foreach ($transition_metadata as $metadata_key => $values) {
        $defaults = $current_transition_filters[$metadata_key] ?? [];
        $element['metadata_filters']['transition_filters'][$metadata_key] = [
          '#type' => 'checkboxes',
          '#title' => $metadata_key,
          '#options' => array_combine($values, $values),
          '#default_value' => is_array($defaults) ? $defaults : [],
        ];
      }
    }

    $element['metadata_filters']['filter_logic'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter logic between keys'),
      '#options' => [
        FilterLogic::And->value => $this->t('AND — must match all keys'),
        FilterLogic::Or->value => $this->t('OR — must match at least one key'),
      ],
      '#default_value' => $this->getStringSetting('filter_logic') ?: FilterLogic::And->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function settingsSummary(): array {
    $summary = [];

    $summary[] = $this->getSetting('is_required')
      ? $this->t('Is required: yes')
      : $this->t('Is required: no');

    $source = OptionLabelSource::tryFrom($this->getStringSetting('option_label_source')) ?? OptionLabelSource::State;
    if ($source === OptionLabelSource::Transition) {
      $summary[] = $this->t('Option labels: transition');
    }

    if ($this->getSetting('show_empty_option')) {
      $summary[] = $this->t('Empty option: shown');
    }

    $comment_mode = CommentMode::tryFrom($this->getStringSetting('comment_mode')) ?? CommentMode::Disabled;
    $comment_label = match ($comment_mode) {
      CommentMode::Optional => $this->t('Optional'),
      CommentMode::Required => $this->t('Required'),
      default => NULL,
    };
    if ($comment_label !== NULL) {
      $summary[] = $this->t('Comment: @mode', ['@mode' => $comment_label]);
    }

    if ($this->getSetting('check_transition_access')) {
      $summary[] = $this->t('Per-transition permission: enforced');
    }

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
      $show_count = 0;
      $hide_count = 0;
      foreach ($conditions as $rule) {
        if (!is_array($rule)) {
          continue;
        }
        if (($rule['visibility'] ?? Visibility::Show->value) === Visibility::Hide->value) {
          $hide_count++;
        }
        else {
          $show_count++;
        }
      }
      $summary[] = $this->t('Conditional rules: @show show, @hide hide', [
        '@show' => $show_count,
        '@hide' => $hide_count,
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
      $logic = FilterLogic::tryFrom($this->getStringSetting('filter_logic')) === FilterLogic::Or ? 'OR' : 'AND';
      $summary[] = $this->t('Filter logic: @logic', ['@logic' => $logic]);
    }

    if ($this->getSetting('show_workflow_diagram')) {
      $summary[] = $this->libraryLocator->isInstalled()
        ? $this->t('Diagram: enabled')
        : $this->t('Diagram: enabled (library missing)');
    }

    if ($this->getSetting('show_history')) {
      $limit = (int) $this->getSetting('history_limit') ?: 5;
      $summary[] = $limit < 0
        ? $this->t('History: all entries')
        : $this->t('History: last @n entries', ['@n' => $limit]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    if ($this->isDefaultValueWidget($form_state)) {
      $element['notice'] = [
        '#markup' => $this->t('The default state is defined by the workflow configuration and cannot be overridden here.'),
      ];
      return $element;
    }

    $state_item = $items[$delta];
    assert($state_item instanceof StateItemInterface);
    $workflow = $this->resolveWorkflow($state_item);
    if ($workflow === NULL) {
      return $element;
    }

    $current_id = $state_item->getId() ?? '';
    $current_label = $state_item->getLabel() ?? '';

    // On new entities the state may not be set yet — resolve default.
    if ($current_id === '') {
      $default_id = $this->defaultStateResolver->getDefault($workflow);
      if ($default_id === NULL) {
        return $element;
      }
      $default_state = $workflow->getState($default_id);
      if ($default_state === NULL) {
        return $element;
      }
      $current_id = $default_state->getId();
      $current_label = $default_state->getLabel();
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
    if ($this->getSetting('check_transition_access')) {
      $allowed_transitions = $this->transitionAccessChecker->filter(
        $workflow->getId(),
        $allowed_transitions,
        $this->currentUser,
      );
    }

    $show_empty_option = (bool) $this->getSetting('show_empty_option');
    $label_source = OptionLabelSource::tryFrom($this->getStringSetting('option_label_source')) ?? OptionLabelSource::State;
    $target_options = $this->optionsBuilder->build(
      $allowed_transitions,
      $current_id,
      $current_label,
      $label_source,
    );

    if (empty($allowed_transitions)) {
      $this->buildNoTransitionElement($element, $current_id);
    }
    else {
      $element['value'] = $this->buildValueElement($target_options, $current_id, $show_empty_option);
      $this->buildCommentElement($element, $form);
      $this->buildDiagramElement($element, $workflow, $current_id, $allowed_transitions);
    }

    $this->buildHistoryElement($element, $state_item->getEntity(), $workflow);

    return $element;
  }

  /**
   * Builds the selector render array honouring the empty-option setting.
   *
   * @param array<string, string> $target_options
   *   Already-deduplicated state ID → label options.
   * @param string $current_id
   *   The current state ID, used as default unless an empty option is requested.
   * @param bool $show_empty_option
   *   When TRUE no value is pre-selected and the empty option label is used.
   *
   * @return array
   *   The render array for the `value` element.
   */
  private function buildValueElement(array $target_options, string $current_id, bool $show_empty_option): array {
    $element = [
      '#type' => 'select',
      '#title' => $this->getStringSetting('select_label') ?: (string) $this->t('Transition to'),
      '#options' => $target_options,
      '#default_value' => $show_empty_option ? NULL : $current_id,
      '#required' => (bool) $this->getSetting('is_required'),
    ];
    if ($show_empty_option) {
      $element['#empty_option'] = $this->getStringSetting('empty_option_label') ?: $this->t('- Choose -');
      $element['#empty_value'] = '';
    }
    return $element;
  }

  /**
   * Adds a transition comment textarea + entity builder when configured.
   *
   * The captured comment is written to the entity's revision log on save by
   * {@see self::storeTransitionComment()}, but only when the entity
   * implements RevisionLogInterface.
   */
  private function buildCommentElement(array &$element, array &$form): void {
    $mode = CommentMode::tryFrom($this->getStringSetting('comment_mode')) ?? CommentMode::Disabled;
    if ($mode === CommentMode::Disabled) {
      return;
    }

    $element['transition_comment'] = [
      '#type' => 'textarea',
      '#title' => $this->getStringSetting('comment_label') ?: $this->t('Transition comment'),
      '#rows' => 3,
      '#required' => $mode === CommentMode::Required,
    ];

    // Run once even with multiple deltas — entity_builders is a flat list.
    if (!in_array([self::class, 'storeTransitionComment'], $form['#entity_builders'] ?? [], TRUE)) {
      $form['#entity_builders'][] = [self::class, 'storeTransitionComment'];
    }
  }

  /**
   * Entity builder: copies the transition comment into the revision log message.
   *
   * Registered through $form['#entity_builders']; runs after every field has
   * populated the entity but before save. No-op when the entity is not
   * revisionable or when the user left the comment empty.
   *
   * @param string $entity_type
   *   The entity type ID, supplied by Drupal's entity builder dispatcher.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity about to be saved.
   * @param array $form
   *   The full form render array (unused here).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Submitted values from the form.
   */
  public static function storeTransitionComment(string $entity_type, EntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    if (!$entity instanceof RevisionLogInterface) {
      return;
    }
    $comment = self::findTransitionComment($form_state->getValues());
    if ($comment === '') {
      return;
    }
    $entity->setNewRevision(TRUE);
    $entity->setRevisionLogMessage($comment);
  }

  /**
   * Recursively finds the first non-empty 'transition_comment' value.
   *
   * The widget is delta-scoped, so the value may sit several nesting levels
   * deep depending on form structure.
   *
   * @param array $values
   *   The full form values tree.
   *
   * @return string
   *   The first non-empty comment string, or '' when none is found.
   */
  private static function findTransitionComment(array $values): string {
    foreach ($values as $value) {
      if (is_array($value)) {
        if (isset($value['transition_comment']) && is_string($value['transition_comment']) && $value['transition_comment'] !== '') {
          return $value['transition_comment'];
        }
        $nested = self::findTransitionComment($value);
        if ($nested !== '') {
          return $nested;
        }
      }
    }
    return '';
  }

  /**
   * Builds the widget element when no transitions are available.
   *
   * Depending on the 'no_transition_behavior' setting, either hides the select
   * silently or displays a configurable warning message.
   *
   * @param array $element
   *   The current element render array, modified in place.
   * @param string $current_id
   *   The current state machine name to preserve as a hidden value.
   */
  protected function buildNoTransitionElement(array &$element, string $current_id): void {
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
  }

  /**
   * Adds a partial Mermaid diagram showing transitions from the current state.
   *
   * Skipped if the diagram setting is disabled, the user lacks permission,
   * or the Mermaid library is not installed.
   *
   * @param array $element
   *   The element render array, modified in place.
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow plugin instance.
   * @param string $current_id
   *   The current state machine name.
   * @param array $allowed_transitions
   *   Allowed (already filtered) transition objects.
   */
  protected function buildDiagramElement(array &$element, WorkflowInterface $workflow, string $current_id, array $allowed_transitions): void {
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
      '#template' => MermaidDiagramBuilder::INLINE_TEMPLATE,
      '#context' => ['diagram' => $mermaid_code],
    ];
    $element['diagram']['#attached']['library'][] = 'state_machine_ui/mermaid';
  }

  /**
   * Applies metadata filters to allowed transitions.
   *
   * Filters target states first (if state filters are configured), then
   * filters transitions by ID (if transition filters are configured).
   *
   * @param string $workflow_id
   *   The workflow plugin ID.
   * @param array $transitions
   *   All allowed transition objects from the state item.
   *
   * @return array
   *   The filtered transition objects.
   */
  protected function applyMetadataFilters(string $workflow_id, array $transitions): array {
    $state_filters = $this->getActiveFilters($this->getArraySetting('state_filters'));
    $transition_filters = $this->getActiveFilters($this->getArraySetting('transition_filters'));

    if (empty($state_filters) && empty($transition_filters)) {
      return $transitions;
    }

    $logic = FilterLogic::tryFrom($this->getStringSetting('filter_logic')) ?? FilterLogic::And;

    if (!empty($state_filters)) {
      $all_target_ids = [];
      foreach ($transitions as $transition) {
        $all_target_ids[] = $transition->getToState()->getId();
      }
      $allowed_states = $this->metadataFilter->filterStates(
        $workflow_id,
        array_unique($all_target_ids),
        $state_filters,
        $logic,
      );
      $transitions = array_filter(
        $transitions,
        static fn(WorkflowTransition $transition): bool => in_array($transition->getToState()->getId(), $allowed_states, TRUE),
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
        static fn(WorkflowTransition $transition, string $transition_id): bool => in_array($transition_id, $allowed_ids, TRUE),
        ARRAY_FILTER_USE_BOTH,
      );
    }

    return $transitions;
  }

  /**
   * Extracts active (checked) filter values from raw widget setting arrays.
   *
   * Checkbox elements store unchecked values as '0' or 0. This method
   * filters them out and returns only the truly selected values.
   *
   * @param array $raw_filters
   *   Raw filter settings indexed by metadata key.
   *
   * @return array<string, string[]>
   *   Active filters with only non-empty, non-zero values.
   */
  protected function getActiveFilters(array $raw_filters): array {
    $active_filters = [];
    foreach ($raw_filters as $metadata_key => $values) {
      if (!is_array($values)) {
        continue;
      }
      $checked_values = array_values(
        array_filter($values, static fn($value): bool => $value !== 0 && $value !== '0' && $value !== '')
      );
      if (!empty($checked_values)) {
        $active_filters[$metadata_key] = $checked_values;
      }
    }
    return $active_filters;
  }

  /**
   * Loads the workflow plugin for a state item, swallowing missing-plugin errors.
   *
   * State Machine's StateItem::getWorkflow() throws PluginNotFoundException
   * when the referenced workflow ID has been deleted. The widget treats this
   * as "render nothing" rather than letting the form crash.
   */
  private function resolveWorkflow(StateItemInterface $state_item): ?WorkflowInterface {
    try {
      return $state_item->getWorkflow();
    }
    catch (PluginException) {
      return NULL;
    }
  }

  /**
   * Renders the transition history table below the selector.
   *
   * Silently skipped when history is disabled, the entity is new (no
   * revisions yet), or the provider returns no entries.
   */
  private function buildHistoryElement(array &$element, EntityInterface $entity, WorkflowInterface $workflow): void {
    if (!$this->getSetting('show_history')) {
      return;
    }
    if (!$entity instanceof FieldableEntityInterface || $entity->isNew()) {
      return;
    }

    $limit = (int) $this->getSetting('history_limit') ?: 5;
    $history = $this->historyProvider->getHistory($entity, $this->fieldDefinition->getName(), $limit);
    if ($history === []) {
      return;
    }

    $rows = [];
    foreach ($history as $entry) {
      $rows[] = [
        $this->dateFormatter->format($entry['timestamp'], 'short'),
        $this->labelForState($workflow, $entry['from']),
        $this->labelForState($workflow, $entry['to']),
        $this->resolveUserLabel($entry['uid']),
        $entry['comment'],
      ];
    }

    $element['history'] = [
      '#type' => 'details',
      '#title' => $this->t('Transition history'),
      '#open' => TRUE,
      '#weight' => 50,
    ];
    $element['history']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('When'),
        $this->t('From'),
        $this->t('To'),
        $this->t('By'),
        $this->t('Comment'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No transitions yet.'),
    ];
  }

  /**
   * Resolves a state ID against the current workflow, with a sensible fallback.
   *
   * An empty state ID maps to "(creation)" so the very first history row
   * reads naturally; a stale state ID falls back to the raw value rather
   * than crashing.
   */
  private function labelForState(WorkflowInterface $workflow, string $state_id): string {
    if ($state_id === '') {
      return '';
    }
    $state = $workflow->getState($state_id);
    return $state !== FALSE && $state !== NULL ? (string) $state->getLabel() : $state_id;
  }

  /**
   * Returns a printable user label for a revision author UID.
   */
  private function resolveUserLabel(int $uid): string {
    if ($uid <= 0) {
      return (string) $this->t('Anonymous');
    }
    $user = User::load($uid);
    return $user !== NULL ? (string) $user->getDisplayName() : (string) $this->t('User @uid', ['@uid' => $uid]);
  }

}
