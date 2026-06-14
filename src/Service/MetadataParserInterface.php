<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Strategy contract for parsing workflow metadata from a given source.
 *
 * Implementations are collected via the tagged service iterator
 * (tag: state_machine_ui.metadata_parser). WorkflowMetadataReader iterates
 * them in priority order and delegates to the first one that supports the
 * workflow ID being parsed.
 *
 * @api
 */
interface MetadataParserInterface {

  /**
   * Returns TRUE when this parser can handle the given workflow.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   *
   * @return bool
   *   TRUE if this parser should be used for $workflow_id.
   */
  public function supports(string $workflow_id): bool;

  /**
   * Parses and returns the metadata for a workflow.
   *
   * @param string $workflow_id
   *   The workflow plugin/entity ID.
   *
   * @return array{states: array<string, array<string, string[]>>, transitions: array<string, array<string, string[]>>}
   *   Parsed metadata split by states and transitions.
   */
  public function parse(string $workflow_id): array;

}
