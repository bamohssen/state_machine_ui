<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;

/**
 * Generates Mermaid stateDiagram-v2 markup from workflow data.
 *
 * @api
 */
interface MermaidDiagramBuilderInterface {

  /**
   * Builds the full diagram for a workflow (horizontal layout).
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow plugin instance.
   *
   * @return string
   *   The Mermaid stateDiagram-v2 markup.
   */
  public function buildFromWorkflow(WorkflowInterface $workflow): string;

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
  public function buildCurrentStateTransitions(WorkflowInterface $workflow, string $current_state_id, array $allowed_transitions): string;

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
  public function build(array $states, array $transitions): string;

}
