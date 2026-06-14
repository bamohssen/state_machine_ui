<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine_ui\Constant\FieldType;
use Drupal\state_machine_ui\Entity\WorkflowMetadataSchema;

/**
 * Form for Workflow Metadata Schema config entities.
 *
 * Manages an AJAX-driven table of field_definitions (key, label, type,
 * description). The schema is a standalone reusable entity: one schema can
 * be referenced by multiple WorkflowStateMachine entities.
 */
final class WorkflowMetadataSchemaForm extends ConfigEntityFormBase {

  /**
   * Form state key used to store field definitions between AJAX calls.
   */
  private const string FIELD_DEFS_KEY = 'field_definitions';

  /**
   * AJAX wrapper ID for the field-definitions table.
   */
  private const string WRAPPER_ID = 'schema-defs-wrapper';

  /**
   * Prefix used in the "Remove" button's name attribute.
   */
  private const string REMOVE_PREFIX = 'remove_def_';

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\state_machine_ui\Entity\WorkflowMetadataSchema $schema */
    $schema = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schema name'),
      '#maxlength' => 255,
      '#default_value' => $schema->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $schema->id(),
      '#machine_name' => [
        'exists' => WorkflowMetadataSchema::class . '::load',
      ],
      '#disabled' => !$schema->isNew(),
    ];

    $this->buildFieldDefsSection($form, $form_state);

    return $form;
  }

  /**
   * Builds the field definitions section with its AJAX-driven table.
   *
   * @param array $form
   *   The form render array, modified in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  private function buildFieldDefsSection(array &$form, FormStateInterface $form_state): void {
    $definitions = $this->getFieldDefinitions($form_state);

    $form['defs_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Field definitions'),
      '#description' => $this->t(
        'Define metadata fields that each workflow state can carry (e.g. <em>tags</em>, <em>category</em>). These can be used for metadata filtering in the widget.'
      ),
      '#open' => TRUE,
    ];

    $form['defs_section']['defs'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Label'),
        $this->t('Key'),
        $this->t('Type'),
        $this->t('Description'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No fields defined yet.'),
      '#prefix' => '<div id="' . self::WRAPPER_ID . '">',
      '#suffix' => '</div>',
    ];

    foreach ($definitions as $index => $definition) {
      $form['defs_section']['defs'][$index] = $this->buildFieldDefRow(
        $index,
        is_array($definition) ? $definition : []
      );
    }

    $form['defs_section']['add_def'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add field'),
      '#submit' => ['::addDefSubmit'],
      '#ajax' => ['callback' => '::ajaxDefs', 'wrapper' => self::WRAPPER_ID],
      '#limit_validation_errors' => [],
      '#button_type' => 'small',
    ];
  }

  /**
   * Builds one row of the field-definitions table.
   *
   * @param int $index
   *   Zero-based position of this row.
   * @param array $definition
   *   Current values for the row (key, label, type, description).
   *
   * @return array
   *   Render array for the table row.
   */
  private function buildFieldDefRow(int $index, array $definition): array {
    $has_key = !empty($definition['key']);

    return [
      'label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#title_display' => 'invisible',
        '#default_value' => $definition['label'] ?? '',
        '#size' => 20,
        '#required' => TRUE,
      ],
      'key' => $has_key
        ? [
          '#type' => 'textfield',
          '#title' => $this->t('Key'),
          '#title_display' => 'invisible',
          '#default_value' => $definition['key'],
          '#disabled' => TRUE,
          '#size' => 15,
        ]
        : [
          '#type' => 'machine_name',
          '#title' => $this->t('Key'),
          '#title_display' => 'invisible',
          '#default_value' => '',
          '#machine_name' => [
            'exists' => [$this, 'fieldDefKeyExists'],
            'source' => ['defs_section', 'defs', $index, 'label'],
            'label' => (string) $this->t('Key'),
          ],
          '#size' => 15,
        ],
      'type' => [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#title_display' => 'invisible',
        '#options' => FieldType::options(),
        '#default_value' => $definition['type'] ?? 'string',
        '#required' => TRUE,
      ],
      'description' => [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#title_display' => 'invisible',
        '#default_value' => $definition['description'] ?? '',
        '#size' => 25,
        '#attributes' => ['placeholder' => $this->t('Help text')],
      ],
      'ops' => [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => self::REMOVE_PREFIX . $index,
        '#submit' => ['::removeDefSubmit'],
        '#ajax' => ['callback' => '::ajaxDefs', 'wrapper' => self::WRAPPER_ID],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['button--small', 'button--danger']],
      ],
    ];
  }

  /**
   * Checks whether a field key is already used within this schema.
   *
   * Called by the #machine_name element's 'exists' callback. Forces a sync
   * first so the freshest POST data is reflected in form_state.
   *
   * @param string $value
   *   The candidate machine name.
   * @param array $element
   *   The #machine_name form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return bool
   *   TRUE if the key is already taken by a different row.
   */
  public function fieldDefKeyExists(string $value, array $element, FormStateInterface $form_state): bool {
    $this->syncFieldDefs($form_state);
    $current_index = $element['#parents'][1] ?? NULL;

    $keys = array_column($form_state->get(self::FIELD_DEFS_KEY) ?? [], 'key');
    $counts = array_count_values(array_filter($keys, static fn($k) => $k !== '' && $k !== NULL));

    if (!isset($counts[$value]) || $counts[$value] === 0) {
      return FALSE;
    }
    // The key exists more than once, or it exists exactly once but not in the
    // current row (meaning another row owns it).
    return $counts[$value] > 1 || ($keys[$current_index] ?? NULL) !== $value;
  }

  /**
   * AJAX callback: returns the refreshed field-definitions table.
   *
   * @param array $form
   *   Current form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   The table render array inside its AJAX wrapper.
   */
  public function ajaxDefs(array &$form, FormStateInterface $form_state): array {
    return $form['defs_section']['defs'];
  }

  /**
   * Submit handler: appends an empty row to the field-definitions table.
   *
   * @param array $form
   *   Current form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state, modified in place.
   */
  public function addDefSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncFieldDefs($form_state);
    $definitions = $form_state->get(self::FIELD_DEFS_KEY) ?? [];
    $definitions[] = ['key' => '', 'label' => '', 'type' => 'string', 'description' => ''];
    $form_state->set(self::FIELD_DEFS_KEY, $definitions);
    $form_state->setRebuild();
  }

  /**
   * Submit handler: removes the row identified by the triggering button's name.
   *
   * @param array $form
   *   Current form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state, modified in place.
   */
  public function removeDefSubmit(array &$form, FormStateInterface $form_state): void {
    $this->syncFieldDefs($form_state);
    $trigger_name = $form_state->getTriggeringElement()['#name'] ?? '';

    if (str_starts_with($trigger_name, self::REMOVE_PREFIX)) {
      $index = (int) substr($trigger_name, strlen(self::REMOVE_PREFIX));
      $definitions = $form_state->get(self::FIELD_DEFS_KEY) ?? [];
      unset($definitions[$index]);
      $form_state->set(self::FIELD_DEFS_KEY, array_values($definitions));
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function entityNoun(): string {
    return (string) $this->t('metadata schema');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\state_machine_ui\Entity\WorkflowMetadataSchema $schema */
    $schema = $this->entity;

    $this->syncFieldDefs($form_state);

    $field_definitions = [];
    foreach ($form_state->get(self::FIELD_DEFS_KEY) ?? [] as $definition) {
      if (empty($definition['key'])) {
        continue;
      }
      $field_definitions[] = [
        'key' => $definition['key'],
        'label' => $definition['label'] ?: $definition['key'],
        'type' => $definition['type'] ?? 'string',
        'description' => $definition['description'] ?? '',
      ];
    }

    $schema->set('field_definitions', $field_definitions);

    return $this->saveAndRedirect($form, $form_state);
  }

  /**
   * Returns field definitions from form_state, initialising from the entity on first call.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array<int, array<string, string>>
   *   Current list of field definitions.
   */
  private function getFieldDefinitions(FormStateInterface $form_state): array {
    if ($form_state->get(self::FIELD_DEFS_KEY) === NULL) {
      $form_state->set(
        self::FIELD_DEFS_KEY,
        $this->entity->getFieldDefinitions()
      );
    }

    return $form_state->get(self::FIELD_DEFS_KEY);
  }

  /**
   * Syncs raw POST input for the defs table back into form_state storage.
   *
   * Machine-name fields are absent from POST when #disabled; their values are
   * preserved from the previous form_state entry.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state, modified in place.
   */
  private function syncFieldDefs(FormStateInterface $form_state): void {
    $raw_input = $form_state->getUserInput();
    $existing = $form_state->get(self::FIELD_DEFS_KEY) ?? [];
    $raw_defs = is_array($raw_input['defs'] ?? NULL) ? $raw_input['defs'] : [];

    $definitions = [];
    foreach ($raw_defs as $index => $raw_def) {
      if (!is_array($raw_def)) {
        continue;
      }
      // Machine name absent from POST when #disabled — fall back to stored value.
      $key = $raw_def['key'] ?? ($existing[$index]['key'] ?? '');
      $definitions[] = [
        'key' => $key,
        'label' => $raw_def['label'] ?? '',
        'type' => $raw_def['type'] ?? 'string',
        'description' => $raw_def['description'] ?? '',
      ];
    }

    $form_state->set(self::FIELD_DEFS_KEY, $definitions);
  }

}
