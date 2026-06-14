<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;

/**
 * Generates Mermaid stateDiagram-v2 markup from workflow data.
 */
final readonly class MermaidDiagramBuilder implements MermaidDiagramBuilderInterface {

  /**
   * Twig template for embedding a Mermaid diagram in a render array.
   *
   * Uses |raw because sanitize() already calls htmlspecialchars(). Twig
   * auto-escaping would double-encode the output otherwise.
   *
   * Usage:
   * @code
   * $build['diagram'] = [
   *   '#type' => 'inline_template',
   *   '#template' => MermaidDiagramBuilder::INLINE_TEMPLATE,
   *   '#context' => ['diagram' => $markup],
   * ];
   * @endcode
   */
  public const string INLINE_TEMPLATE = '<div class="state-machine-ui-mermaid-diagram"><pre class="mermaid">{{ diagram|raw }}</pre></div>';

  /**
   * Builds the full diagram for a workflow (horizontal layout).
   *
   * Iterates all states and transitions from the workflow plugin instance and
   * delegates to build() with the raw arrays.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow plugin instance.
   *
   * @return string
   *   The Mermaid stateDiagram-v2 markup.
   */
  public function buildFromWorkflow(WorkflowInterface $workflow): string {
    $states = [];
    foreach ($workflow->getStates() as $state_id => $state) {
      $states[] = ['key' => $state_id, 'label' => $state->getLabel()];
    }

    $transitions = [];
    foreach ($workflow->getTransitions() as $transition) {
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
   *   Allowed transitions (already filtered by guards and metadata filters).
   *
   * @return string
   *   The Mermaid stateDiagram-v2 markup.
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

      $transition_label = $transition->getLabel();
      $line = '  ' . $safe_current . ' --> ' . $safe_to;
      if ($transition_label !== '') {
        $line .= ' : ' . $this->sanitize($transition_label);
      }
      $lines[] = $line;
    }

    return implode("\n", $lines);
  }

  /**
   * Builds a Mermaid stateDiagram-v2 string from raw arrays (horizontal).
   *
   * @param array<int, array{key: string, label: string}> $states
   *   State definitions with 'key' and 'label' keys.
   * @param array<int, array{label: string, from: string[], to: string}> $transitions
   *   Transition definitions with 'label', 'from', and 'to' keys.
   *
   * @return string
   *   The Mermaid stateDiagram-v2 markup.
   */
  public function build(array $states, array $transitions): string {
    $lines = ['stateDiagram-v2', '  direction LR'];

    foreach ($states as $state) {
      $state_key = $state['key'] ?? '';
      if ($state_key === '') {
        continue;
      }
      $state_label = $state['label'] ?? $state_key;
      $lines[] = '  ' . $this->sanitize($state_key) . ' : ' . $this->sanitize($state_label);
    }

    foreach ($transitions as $transition) {
      $to_key = $transition['to'] ?? '';
      if ($to_key === '') {
        continue;
      }
      $transition_label = $transition['label'] ?? '';
      $safe_to = $this->sanitize($to_key);

      foreach ($transition['from'] ?? [] as $from_key) {
        if ($from_key === '') {
          continue;
        }
        $line = '  ' . $this->sanitize($from_key) . ' --> ' . $safe_to;
        if ($transition_label !== '') {
          $line .= ' : ' . $this->sanitize($transition_label);
        }
        $lines[] = $line;
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Sanitizes a string for safe inclusion in Mermaid diagram syntax.
   *
   * Strips characters that have special meaning in stateDiagram-v2:
   *  - Colons are used as label separators.
   *  - Newlines break the line-based syntax.
   *  - Quotes and backticks can escape/break node aliases.
   *  - HTML special chars are entity-encoded to prevent XSS when the diagram
   *    is rendered by the browser via mermaid.js.
   *
   * @param string $value
   *   The raw string to sanitize.
   *
   * @return string
   *   The sanitized string safe for Mermaid output.
   */
  private function sanitize(string $value): string {
    // Strip Mermaid-unsafe chars first, then HTML-encode for browser safety.
    // Order matters: htmlspecialchars() runs after stripping so it never
    // double-encodes chars that were already removed.
    $value = str_replace([':', "\n", "\r", '"', '`'], [' ', ' ', '', '', ''], $value);
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

}
