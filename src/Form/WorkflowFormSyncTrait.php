<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides raw-POST → form_state sync helpers for WorkflowForm.
 *
 * Extracted from WorkflowForm to keep the main class focused on orchestration.
 * All methods here are intended for use only by WorkflowForm.
 *
 * @internal
 */
trait WorkflowFormSyncTrait {

  /**
   * Syncs all raw POST input sections back into form_state storage.
   *
   * Call this at the start of every AJAX submit handler so that subsequent
   * form builds see the latest user input rather than stale stored data.
   *
   * Sections synced: state_metadata_schema, transition_metadata_schema,
   * states, default_state.
   */
  private function syncAll(FormStateInterface $form_state): void {
    $input = $form_state->getUserInput();

    if (array_key_exists('state_metadata_schema', $input)) {
      $form_state->set('state_metadata_schema', (string) $input['state_metadata_schema']);
    }
    if (array_key_exists('transition_metadata_schema', $input)) {
      $form_state->set('transition_metadata_schema', (string) $input['transition_metadata_schema']);
    }

    $this->syncStates($form_state, $input);

    if (array_key_exists('default_state', $input)) {
      $form_state->set('default_state', (string) $input['default_state']);
    }
  }

  /**
   * Syncs the raw states_list POST data into form_state.
   *
   * Machine-name keys are absent when #disabled; they are preserved from the
   * previously stored state. Field values live in the metadata sub-form and are
   * always preserved from storage rather than overwritten from POST input.
   */
  private function syncStates(FormStateInterface $form_state, array $input): void {
    $raw_states = is_array($input['states_list'] ?? NULL) ? $input['states_list'] : [];
    $existing_states = $form_state->get('states') ?? [];
    $parsed_states = [];
    $position = 0;

    foreach ($raw_states as $index => $state_input) {
      if (!is_array($state_input)) {
        continue;
      }
      // Machine name absent from POST when #disabled — fall back to stored value.
      $key = $state_input['key'] ?? ($existing_states[$index]['key'] ?? '');
      // Field values live in the metadata sub-form and are not in the main POST.
      $preserved_fields = is_array($existing_states[$index]['fields'] ?? NULL)
        ? $existing_states[$index]['fields']
        : [];

      $parsed_states[] = [
        'key' => $key,
        'label' => $state_input['label'] ?? '',
        'description' => $state_input['description'] ?? '',
        'weight' => isset($state_input['weight']) ? (int) $state_input['weight'] : $position,
        'fields' => $preserved_fields,
      ];
      $position++;
    }

    usort($parsed_states, static fn(array $a, array $b): int => $a['weight'] <=> $b['weight']);
    $form_state->set('states', $parsed_states);
  }

  /**
   * Returns stored form_state data for $key, seeding from $default on first call.
   */
  private function seedFormStateData(FormStateInterface $form_state, string $key, array $default): array {
    if ($form_state->get($key) === NULL) {
      $form_state->set($key, $default ?: []);
    }
    return $form_state->get($key);
  }

  /**
   * Extracts the row index from the triggering element's name attribute.
   *
   * Button names follow the pattern "{prefix}{index}", e.g. "remove_state_2".
   */
  private function triggerIndex(FormStateInterface $form_state, string $prefix): ?int {
    $name = $form_state->getTriggeringElement()['#name'] ?? '';
    return str_starts_with($name, $prefix) ? (int) substr($name, strlen($prefix)) : NULL;
  }

}
