<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine_ui\Constraint\MetadataValueConstraint;
use Drupal\state_machine_ui\Constant\FieldType;

/**
 * Builds the states section render array for WorkflowForm.
 *
 * Extracted from WorkflowForm to satisfy SRP. WorkflowForm remains the
 * AJAX/submit orchestrator; this class is responsible for building state
 * and per-state-metadata render arrays.
 *
 * AJAX callbacks (e.g. '::ajaxStates') and machine_name exists callbacks
 * must be resolved by Drupal against the form class, so callers pass those
 * as injected callables.
 *
 * @internal
 */
final class StatesTableBuilder {

  use StringTranslationTrait;

  /**
   * Builds the complete states section render array.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state (must already contain 'states', 'default_state',
   *   and optionally 'state_metadata_edit_index').
   * @param array $field_definitions
   *   Field definitions from the referenced WorkflowMetadataSchema.
   * @param callable $state_key_exists_cb
   *   The machine_name 'exists' callback for state keys — typically
   *   [$workflow_form, 'stateKeyExists'].
   *
   * @return array
   *   Render array for the states_section details element, including the
   *   default-state select, the draggable table, and the metadata sub-form.
   */
  public function build(FormStateInterface $form_state, array $field_definitions, callable $state_key_exists_cb): array {
    $states = $form_state->get('states') ?? [];
    $edit_index = $form_state->get('state_metadata_edit_index');

    $section = [
      '#type' => 'details',
      '#title' => $this->t('States'),
      '#description' => $this->t(
        'Define all workflow states. Drag rows to reorder them. Pick the default (initial) state above. <strong>Add states before transitions</strong> — the transition options come from here.'
      ),
      '#open' => TRUE,
      '#prefix' => '<div id="states-wrapper">',
      '#suffix' => '</div>',
    ];

    $section['default_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Default state'),
      '#description' => $this->t('State used as initial value for new entities.'),
      '#options' => $this->buildSelectOptions($states),
      '#default_value' => $form_state->get('default_state'),
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];

    $section['states_list'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Label'),
        $this->t('Machine name'),
        $this->t('Description'),
        $this->t('Metadata'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No states defined yet.'),
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'state-weight',
      ],
      ],
      '#attributes' => ['id' => 'states-table'],
    ];

    foreach ($states as $index => $state) {
      $section['states_list'][$index] = $this->buildStateRow(
        (int) $index,
        $state,
        $edit_index === $index,
        $state_key_exists_cb,
      );
    }

    $section['add_state'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add state'),
      '#submit' => ['::addStateSubmit'],
      '#ajax' => ['callback' => '::ajaxStates', 'wrapper' => 'states-wrapper'],
      '#limit_validation_errors' => [],
      '#button_type' => 'small',
    ];

    $section['metadata_edit'] = [
      '#type' => 'container',
      '#prefix' => '<div id="state-metadata-edit-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($edit_index !== NULL && isset($states[$edit_index]) && !empty($field_definitions)) {
      $this->buildMetadataEditSubForm(
        $section['metadata_edit'],
        (int) $edit_index,
        $states[$edit_index],
        $field_definitions,
      );
    }

    return $section;
  }

  /**
   * Builds a keyed label map from the stored states array.
   *
   * @param array $states
   *   State data from form_state.
   *
   * @return array<string, string>
   *   Map of state machine-name => label.
   */
  public function buildSelectOptions(array $states): array {
    $options = [];
    foreach ($states as $state) {
      $key = $state['key'] ?? '';
      if ($key !== '') {
        $options[$key] = $state['label'] ?: $key;
      }
    }
    return $options;
  }

  /**
   * Builds a single draggable state row.
   */
  private function buildStateRow(int $index, array $state, bool $is_editing, callable $exists_cb): array {
    return [
      '#attributes' => ['class' => ['draggable']],
      '#weight' => (int) ($state['weight'] ?? $index),
      'label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#title_display' => 'invisible',
        '#default_value' => $state['label'] ?? '',
        '#size' => 25,
        '#required' => TRUE,
      ],
      'key' => !empty($state['key'])
        ? [
          '#type' => 'textfield',
          '#title' => $this->t('Machine name'),
          '#title_display' => 'invisible',
          '#default_value' => $state['key'],
          '#disabled' => TRUE,
          '#size' => 20,
        ]
        : [
          '#type' => 'machine_name',
          '#title' => $this->t('Machine name'),
          '#title_display' => 'invisible',
          '#default_value' => '',
          '#machine_name' => [
            'exists' => $exists_cb,
            'source' => ['states_section', 'states_list', $index, 'label'],
            'label' => (string) $this->t('Machine name'),
          ],
          '#size' => 20,
        ],
      'description' => [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#title_display' => 'invisible',
        '#default_value' => $state['description'] ?? '',
        '#size' => 30,
        '#attributes' => ['placeholder' => $this->t('Optional')],
      ],
      'metadata_summary' => [
        '#markup' => $this->renderMetadataSummary($state['fields'] ?? []),
      ],
      'weight' => [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @label', [
          '@label' => $state['label'] ?: ($state['key'] ?? $index),
        ]),
        '#title_display' => 'invisible',
        '#default_value' => (int) ($state['weight'] ?? $index),
        '#delta' => 100,
        '#attributes' => ['class' => ['state-weight']],
      ],
      'ops' => [
        '#type' => 'actions',
        'edit_metadata' => [
          '#type' => 'submit',
          '#value' => $is_editing ? $this->t('Close metadata') : $this->t('Edit metadata'),
          '#name' => 'edit_state_metadata_' . $index,
          '#submit' => ['::editStateMetadataSubmit'],
          '#ajax' => ['callback' => '::ajaxStates', 'wrapper' => 'states-wrapper'],
          '#limit_validation_errors' => [],
          '#button_type' => 'small',
        ],
        'remove' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_state_' . $index,
          '#submit' => ['::removeStateSubmit'],
          '#ajax' => ['callback' => '::ajaxStates', 'wrapper' => 'states-wrapper'],
          '#limit_validation_errors' => [],
          '#attributes' => ['class' => ['button--small', 'button--danger']],
        ],
      ],
    ];
  }

  /**
   * Populates the metadata_edit container with the inline sub-form.
   */
  private function buildMetadataEditSubForm(array &$container, int $index, array $state, array $field_definitions): void {
    $container['inner'] = [
      '#type' => 'details',
      '#title' => $this->t('Metadata for state: @label', [
        '@label' => $state['label'] ?: $state['key'],
      ]),
      '#open' => TRUE,
    ];

    $container['inner']['fields'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#parents' => ['state_metadata_edit', 'fields'],
    ];

    $existing_values = $state['fields'] ?? [];
    $valid_definitions = array_filter(
      $field_definitions,
      static fn(array $def): bool => ($def['key'] ?? '') !== ''
    );

    foreach ($valid_definitions as $field_definition) {
      $field_key = $field_definition['key'];
      $container['inner']['fields'][$field_key] = $this->buildDynamicField(
        $field_definition,
        $existing_values[$field_key] ?? NULL
      );
    }

    $container['inner']['actions'] = [
      '#type' => 'actions',
      'apply' => [
        '#type' => 'submit',
        '#value' => $this->t('Apply metadata'),
        '#name' => 'apply_state_metadata_' . $index,
        '#submit' => ['::applyStateMetadataSubmit'],
        '#ajax' => ['callback' => '::ajaxStates', 'wrapper' => 'states-wrapper'],
        '#limit_validation_errors' => [['state_metadata_edit']],
      ],
    ];
  }

  /**
   * Builds a dynamic field element for a given field definition.
   *
   * String and List fields get a MetadataValueConstraint validate callback.
   */
  private function buildDynamicField(array $definition, mixed $value): array {
    $type = FieldType::fromString($definition['type'] ?? 'string');
    $label = $definition['label'] ?? $definition['key'];
    $description = $definition['description'] ?? '';

    return match ($type) {
      FieldType::List => [
        '#type' => 'textarea',
        '#title' => $label,
        '#default_value' => is_array($value) ? implode("\n", $value) : ($value ?? ''),
        '#description' => ($description ? $description . ' ' : '') . (string) $this->t('One value per line. Allowed: lowercase letters, digits, underscores.'),
        '#rows' => 3,
        '#element_validate' => [[MetadataValueConstraint::class, 'validateFormElement']],
      ],
      FieldType::Boolean => [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => (bool) ($value ?? FALSE),
        '#description' => $description,
      ],
      FieldType::Number => [
        '#type' => 'number',
        '#title' => $label,
        '#default_value' => $value,
        '#description' => $description,
        '#step' => 'any',
      ],
      FieldType::String => [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $value ?? '',
        '#description' => ($description ? $description . ' ' : '') . (string) $this->t('Allowed: lowercase letters, digits, underscores.'),
        '#element_validate' => [[MetadataValueConstraint::class, 'validateFormElement']],
      ],
    };
  }

  /**
   * Builds a multi-line, HTML-safe summary of a state's metadata values.
   *
   * One "key: value" pair per line. Fields whose value is empty (or whose
   * key is empty) are skipped; when nothing remains, an em dash is shown.
   *
   * @param array<string, mixed> $fields
   *   Raw metadata values keyed by schema field key.
   */
  private function renderMetadataSummary(array $fields): string {
    $lines = [];
    foreach ($fields as $key => $value) {
      $key = (string) $key;
      if ($key === '') {
        continue;
      }
      $formatted = $this->formatMetadataValue($value);
      if ($formatted === '') {
        continue;
      }
      $lines[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . ': ' . $formatted;
    }

    if ($lines === []) {
      return '<em>' . htmlspecialchars((string) $this->t('No metadata'), ENT_QUOTES, 'UTF-8') . '</em>';
    }

    return implode('<br>', $lines);
  }

  /**
   * Formats a single metadata value, returning '' when nothing is displayable.
   *
   * @param mixed $value
   *   List of strings, scalar, or anything castable to string.
   */
  private function formatMetadataValue(mixed $value): string {
    if (is_array($value)) {
      $items = array_values(array_filter(
        array_map(static fn($item): string => (string) $item, $value),
        static fn(string $item): bool => $item !== '',
      ));
      if ($items === []) {
        return '';
      }
      $shown = array_slice($items, 0, 3);
      $more = count($items) - count($shown);
      $rendered = htmlspecialchars(implode(', ', $shown), ENT_QUOTES, 'UTF-8');
      return $more > 0 ? $rendered . ' (+' . $more . ')' : $rendered;
    }
    if (is_bool($value)) {
      return $value ? (string) $this->t('yes') : (string) $this->t('no');
    }
    $string = trim((string) $value);
    if ($string === '') {
      return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
  }

}
