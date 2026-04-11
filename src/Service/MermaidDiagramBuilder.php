<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;

/**
 * Generates Mermaid stateDiagram-v2 markup from workflow data.
 */
final class MermaidDiagramBuilder {

  /**
   * Builds the full diagram for a workflow (horizontal layout).
   */
  public function buildFromWorkflow(WorkflowInterface $workflow): string {
    $states = [];
    foreach ($workflow->getStates() as $id => $state) {
      $states[] = ['key' => $id, 'label' => $state->getLabel()];
    }

    $transitions = [];
    foreach ($workflow->getTransitions() as $id => $transition) {
      $transitions[] = [
        'label' => $transition->getLabel(),
        'from' => array_keys($transition->getFromStates()),
        'to' => $transition->getToState()->getId(),
      ];
    }

    return $this->build($states, $transitions);
  }

  /**
   * Builds a partial diagram showing only transitions from the current state.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow plugin instance.
   * @param string $current_state_id
   *   The current state ID.
   * @param array $allowed_transitions
   *   Allowed transitions (already filtered by guards).
   *
   * @return string
   *   The Mermaid diagram markup.
   */
  public function buildCurrentStateTransitions(WorkflowInterface $workflow, string $current_state_id, array $allowed_transitions): string {
    $lines = ['stateDiagram-v2', '  direction LR'];

    $current_state = $workflow->getState($current_state_id);
    $current_label = $current_state ? $current_state->getLabel() : $current_state_id;
    $safe_current = $this->sanitize($current_state_id);
    $lines[] = '  ' . $safe_current . ' : ' . $this->sanitize($current_label);

    $seen_targets = [];
    foreach ($allowed_transitions as $transition) {
      $to_state = $transition->getToState();
      $to_id = $to_state->getId();
      $to_label = $to_state->getLabel();
      $safe_to = $this->sanitize($to_id);

      if (!isset($seen_targets[$to_id])) {
        $lines[] = '  ' . $safe_to . ' : ' . $this->sanitize($to_label);
        $seen_targets[$to_id] = TRUE;
      }

      $t_label = $transition->getLabel();
      $line = '  ' . $safe_current . ' --> ' . $safe_to;
      if ($t_label !== '') {
        $line .= ' : ' . $this->sanitize($t_label);
      }
      $lines[] = $line;
    }

    return implode("\n", $lines);
  }

  /**
   * Builds a Mermaid stateDiagram-v2 string from raw arrays (horizontal).
   */
  public function build(array $states, array $transitions): string {
    $lines = ['stateDiagram-v2', '  direction LR'];

    foreach ($states as $state) {
      $key = $state['key'] ?? '';
      if ($key === '') {
        continue;
      }
      $label = $state['label'] ?? $key;
      $lines[] = '  ' . $this->sanitize($key) . ' : ' . $this->sanitize($label);
    }

    foreach ($transitions as $transition) {
      $to = $transition['to'] ?? '';
      if ($to === '') {
        continue;
      }
      $label = $transition['label'] ?? '';
      $safe_to = $this->sanitize($to);

      foreach ($transition['from'] ?? [] as $from) {
        if ($from === '') {
          continue;
        }
        $line = '  ' . $this->sanitize($from) . ' --> ' . $safe_to;
        if ($label !== '') {
          $line .= ' : ' . $this->sanitize($label);
        }
        $lines[] = $line;
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Sanitizes a string for Mermaid syntax.
   */
  private function sanitize(string $value): string {
    return str_replace([':', "\n", "\r"], [' ', ' ', ''], $value);
  }

}
