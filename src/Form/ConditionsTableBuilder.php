<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\WorkflowManagerInterface;

/**
 * Builds the AJAX table for conditional field rules in the widget settings.
 */
final class ConditionsTableBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly WorkflowManagerInterface $workflowManager,
  ) {}

  /**
   * Builds the conditions table form element.
   */
  public function build(mixed $conditions, FieldDefinitionInterface $field_definition, FormStateInterface $form_state): array {
    $conditions = $this->normalizeConditions($conditions);
    $wrapper_id = 'conditions-table-wrapper';

    $element = [
      '#type' => 'details',
      '#title' => $this->t('Conditional field rules'),
      '#description' => $this->t('Fields referenced in rules are hidden by default. They become visible when the matching target state is selected. If marked required, validation is enforced server-side.'),
      '#open' => !empty($conditions),
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $stored = $form_state->get('state_field_rules_conditions');
    if ($stored === NULL) {
      $stored = $conditions;
      $form_state->set('state_field_rules_conditions', $stored);
    }
    $stored = $this->normalizeConditions($stored);

    $state_options = $this->getStateOptions($field_definition);
    $field_options = $this->getFieldOptions($field_definition);

    $element['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Target state'),
        $this->t('Field'),
        $this->t('Required'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No rules defined. Click "Add rule" to create one.'),
    ];

    foreach ($stored as $index => $rule) {
      $element['table'][$index] = $this->buildRow((int) $index, $rule, $state_options, $field_options, $wrapper_id);
    }

    $element['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add rule'),
      '#name' => 'conditions_add',
      '#submit' => [[static::class, 'addRuleSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => $wrapper_id,
      ],
      '#limit_validation_errors' => [],
      '#button_type' => 'small',
    ];

    return $element;
  }

  private function buildRow(int $index, array $rule, array $state_options, array $field_options, string $wrapper_id): array {
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
      'required' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Required'),
        '#title_display' => 'invisible',
        '#default_value' => !empty($rule['required']),
      ],
      'remove' => [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'conditions_remove_' . $index,
        '#submit' => [[static::class, 'removeRuleSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['button--small', 'button--danger']],
      ],
    ];
  }

  private function normalizeConditions(mixed $conditions): array {
    if (!is_array($conditions)) {
      return [];
    }
    $clean = [];
    foreach ($conditions as $rule) {
      if (!is_array($rule)) {
        continue;
      }
      $clean[] = [
        'state' => (string) ($rule['state'] ?? ''),
        'field' => (string) ($rule['field'] ?? ''),
        'required' => !empty($rule['required']),
      ];
    }
    return $clean;
  }

  private function getStateOptions(FieldDefinitionInterface $field_definition): array {
    $workflow_id = $field_definition->getSetting('workflow');
    if (empty($workflow_id)) {
      return [];
    }
    try {
      $workflow = $this->workflowManager->createInstance($workflow_id);
    }
    catch (\Exception) {
      return [];
    }
    $options = [];
    foreach ($workflow->getStates() as $id => $state) {
      $options[$id] = $state->getLabel();
    }
    return $options;
  }

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

  public static function ajaxCallback(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'];
    $element = $form;
    foreach ($parents as $key) {
      if (isset($element[$key])) {
        $element = $element[$key];
      }
      if ($key === 'conditions') {
        break;
      }
    }
    return $element;
  }

  public static function addRuleSubmit(array &$form, FormStateInterface $form_state): void {
    $conditions = $form_state->get('state_field_rules_conditions');
    if (!is_array($conditions)) {
      $conditions = [];
    }
    $conditions[] = ['state' => '', 'field' => '', 'required' => FALSE];
    $form_state->set('state_field_rules_conditions', $conditions);
    $form_state->setRebuild();
  }

  public static function removeRuleSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $name = $trigger['#name'] ?? '';
    if (preg_match('/^conditions_remove_(\d+)$/', $name, $matches)) {
      $index = (int) $matches[1];
      $conditions = $form_state->get('state_field_rules_conditions');
      if (!is_array($conditions)) {
        $conditions = [];
      }
      unset($conditions[$index]);
      $form_state->set('state_field_rules_conditions', array_values($conditions));
    }
    $form_state->setRebuild();
  }

}
