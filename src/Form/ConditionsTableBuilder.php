<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_ui\Constant\Visibility;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the AJAX table for conditional field rules in the widget settings.
 *
 * Handles the add/remove submit handlers and the AJAX callback as static
 * methods so they are callable from the widget without circular dependencies.
 */
final class ConditionsTableBuilder {

  use StringTranslationTrait;

  private const string WRAPPER_ID = 'conditions-table-wrapper';
  private const string FORM_STATE_KEY = 'state_field_rules_conditions';
  private const string REMOVE_PREFIX = 'conditions_remove_';

  /**
   * Constructs a ConditionsTableBuilder instance.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The workflow plugin manager.
   */
  public function __construct(
    #[Autowire(service: 'entity_field.manager')]
    private readonly EntityFieldManagerInterface $entityFieldManager,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'plugin.manager.workflow')]
    private readonly WorkflowManagerInterface $workflowManager,
  ) {}

  /**
   * Builds the conditions table form element for widget settings.
   *
   * @param mixed $conditions
   *   Raw conditions value from widget settings (normalized internally).
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition for the state field using this widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The render array for the conditions details element.
   */
  public function build(mixed $conditions, FieldDefinitionInterface $field_definition, FormStateInterface $form_state): array {
    $conditions = $this->normalizeConditions($conditions);

    $stored = $form_state->get(self::FORM_STATE_KEY);
    if ($stored === NULL) {
      $stored = $conditions;
      $form_state->set(self::FORM_STATE_KEY, $stored);
    }
    $stored = $this->normalizeConditions($stored);

    $element = [
      '#type' => 'details',
      '#title' => $this->t('Conditional field rules'),
      '#description' => $this->t('<p><strong>Purpose:</strong> show certain fields only at the right moment. For example, a "Rejection reason" that appears only when an article is sent back to draft.</p><p><strong>Behavior:</strong></p><ul><li>Without a rule, the field is visible as usual.</li><li>With a "Show" rule, the field appears only for the chosen states.</li><li>With only "Hide" rules, the field is visible everywhere except for the chosen states.</li><li>When "Required" is checked, the field is only required while it is visible.</li></ul>'),
      '#open' => !empty($stored),
      '#prefix' => '<div id="' . self::WRAPPER_ID . '">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#element_validate' => [[self::class, 'extractConditions']],
    ];

    $state_options = $this->getStateOptions($field_definition);
    $field_options = $this->getFieldOptions($field_definition);

    $element['table'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Target state'),
        $this->t('Field'),
        $this->t('Visibility'),
        $this->t('Required'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No rules defined. Click "Add rule" to create one.'),
    ];

    foreach ($stored as $index => $rule) {
      $element['table'][$index] = $this->buildRow((int) $index, $rule, $state_options, $field_options);
    }

    $element['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add rule'),
      '#name' => 'conditions_add',
      '#submit' => [[static::class, 'addRuleSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => self::WRAPPER_ID,
      ],
      '#limit_validation_errors' => [],
      '#button_type' => 'small',
    ];

    return $element;
  }

  /**
   * Builds a single row of the conditions table.
   *
   * @param int $index
   *   Zero-based row index.
   * @param array $rule
   *   The rule data (state, field, visibility, required).
   * @param array $state_options
   *   Available state options for the select element.
   * @param array $field_options
   *   Available field options for the select element.
   *
   * @return array
   *   The render array for the table row.
   */
  private function buildRow(int $index, array $rule, array $state_options, array $field_options): array {
    return [
      'state' => [
        '#type' => 'select',
        '#title' => $this->t('State'),
        '#title_display' => 'invisible',
        '#options' => ['' => $this->t('- Select -')] + $state_options,
        '#default_value' => $rule['state'] ?? '',
      ],
      'field' => [
        '#type' => 'select',
        '#title' => $this->t('Field'),
        '#title_display' => 'invisible',
        '#options' => ['' => $this->t('- Select -')] + $field_options,
        '#default_value' => $rule['field'] ?? '',
      ],
      'visibility' => [
        '#type' => 'select',
        '#title' => $this->t('Visibility'),
        '#title_display' => 'invisible',
        '#options' => [
          Visibility::Show->value => $this->t('Show'),
          Visibility::Hide->value => $this->t('Hide'),
        ],
        '#default_value' => $rule['visibility'] ?? Visibility::Show->value,
      ],
      'required' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Required'),
        '#title_display' => 'invisible',
        '#default_value' => !empty($rule['required']),
      ],
      'remove' => [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => self::REMOVE_PREFIX . $index,
        '#submit' => [[static::class, 'removeRuleSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'ajaxCallback'],
          'wrapper' => self::WRAPPER_ID,
        ],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['button--small', 'button--danger']],
      ],
    ];
  }

  /**
   * Normalizes a raw conditions value to a clean indexed array of rules.
   *
   * Discards non-array entries and enforces valid visibility values.
   *
   * @param mixed $conditions
   *   Raw value from form settings or form state.
   *
   * @return array<int, array{state: string, field: string, visibility: string, required: bool}>
   *   Cleaned conditions array.
   */
  private function normalizeConditions(mixed $conditions): array {
    if (!is_array($conditions)) {
      return [];
    }
    $clean_conditions = [];
    foreach ($conditions as $rule) {
      if (!is_array($rule)) {
        continue;
      }
      $visibility = $rule['visibility'] ?? Visibility::Show->value;
      if ($visibility !== Visibility::Hide->value) {
        $visibility = Visibility::Show->value;
      }
      $clean_conditions[] = [
        'state' => (string) ($rule['state'] ?? ''),
        'field' => (string) ($rule['field'] ?? ''),
        'visibility' => $visibility,
        'required' => !empty($rule['required']),
      ];
    }
    return $clean_conditions;
  }

  /**
   * Builds the available state options from the field's workflow setting.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The state field definition.
   *
   * @return array<string, string>
   *   State ID → label options, or empty array if the workflow cannot be loaded.
   */
  private function getStateOptions(FieldDefinitionInterface $field_definition): array {
    $workflow_id = $field_definition->getSetting('workflow');
    if (empty($workflow_id)) {
      return [];
    }
    try {
      $workflow = $this->workflowManager->createInstance($workflow_id);
    }
    catch (PluginException) {
      return [];
    }
    $options = [];
    foreach ($workflow->getStates() as $state_id => $state) {
      $options[$state_id] = $state->getLabel();
    }
    return $options;
  }

  /**
   * Builds the available field options from the entity form display.
   *
   * Excludes the state field itself. Only fields that appear on the default
   * form display and have a known field definition are included.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The state field definition.
   *
   * @return array<string, string>
   *   Field name → label options, sorted alphabetically.
   */
  private function getFieldOptions(FieldDefinitionInterface $field_definition): array {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $bundle = $field_definition->getTargetBundle();
    $state_field_name = $field_definition->getName();
    if (empty($entity_type) || empty($bundle)) {
      return [];
    }
    $display = $this->entityTypeManager->getStorage('entity_form_display')->load("{$entity_type}.{$bundle}.default");
    if ($display === NULL) {
      return [];
    }
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $options = [];
    foreach ($display->getComponents() as $field_name => $component) {
      if ($field_name === $state_field_name || !isset($field_definitions[$field_name])) {
        continue;
      }
      $options[$field_name] = (string) $field_definitions[$field_name]->getLabel();
    }
    asort($options);
    return $options;
  }

  /**
   * AJAX callback: returns the refreshed conditions details element.
   *
   * Uses NestedArray::getValue() with the path up to and including 'conditions'
   * to safely locate the element without fragile sequential iteration.
   *
   * @param array $form
   *   The current form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The conditions form element to replace via AJAX.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'] ?? [];
    $pos = array_search('conditions', $parents, TRUE);
    if ($pos !== FALSE) {
      $element_parents = array_slice($parents, 0, (int) $pos + 1);
      $element = NestedArray::getValue($form, $element_parents);
      if (is_array($element)) {
        return $element;
      }
    }
    return $form;
  }

  /**
   * Element validator: flattens the conditions sub-tree to the schema shape.
   *
   * Without this the saved settings would look like
   *   conditions: { table: { 0: { state: …, remove: '' }, … }, add: 'Add rule' }
   * which neither passes schema validation nor matches the widget runtime.
   */
  public static function extractConditions(array &$element, FormStateInterface $form_state): void {
    $parents = $element['#parents'] ?? [];
    $raw_rows = $form_state->getValue([...$parents, 'table']) ?? [];
    if (!is_array($raw_rows)) {
      $form_state->setValue($parents, []);
      return;
    }

    $allowed_visibility = [Visibility::Show->value, Visibility::Hide->value];
    $clean = [];
    foreach ($raw_rows as $row) {
      if (!is_array($row) || empty($row['state']) || empty($row['field'])) {
        continue;
      }
      $visibility = $row['visibility'] ?? Visibility::Show->value;
      if (!in_array($visibility, $allowed_visibility, TRUE)) {
        $visibility = Visibility::Show->value;
      }
      $clean[] = [
        'state' => (string) $row['state'],
        'field' => (string) $row['field'],
        'visibility' => $visibility,
        'required' => !empty($row['required']),
      ];
    }

    $form_state->setValue($parents, $clean);
  }

  /**
   * Submit handler: appends an empty rule to the conditions list.
   *
   * @param array $form
   *   The current form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state, modified in place.
   */
  public static function addRuleSubmit(array &$form, FormStateInterface $form_state): void {
    $conditions = $form_state->get(self::FORM_STATE_KEY);
    if (!is_array($conditions)) {
      $conditions = [];
    }
    $conditions[] = [
      'state' => '',
      'field' => '',
      'visibility' => Visibility::Show->value,
      'required' => FALSE,
    ];
    $form_state->set(self::FORM_STATE_KEY, $conditions);
    $form_state->setRebuild();
  }

  /**
   * Submit handler: removes the rule identified by the triggering button's name.
   *
   * @param array $form
   *   The current form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state, modified in place.
   */
  public static function removeRuleSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $trigger_name = $trigger['#name'] ?? '';
    $prefix = preg_quote(self::REMOVE_PREFIX, '/');
    if (preg_match('/^' . $prefix . '(\d+)$/', $trigger_name, $matches)) {
      $index = (int) $matches[1];
      $conditions = $form_state->get(self::FORM_STATE_KEY);
      if (!is_array($conditions)) {
        $conditions = [];
      }
      unset($conditions[$index]);
      $form_state->set(self::FORM_STATE_KEY, array_values($conditions));
    }
    $form_state->setRebuild();
  }

}
