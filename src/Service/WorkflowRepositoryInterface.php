<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * High-level query API for workflow states, transitions and metadata.
 *
 * This service is the primary entry point for developers who need to:
 *   - Inspect what transitions are available for an entity.
 *   - Check whether a specific transition is allowed (with optional metadata
 *     constraints on the target state).
 *   - Retrieve states or transitions that carry specific metadata values.
 *
 * All methods that receive an entity and a field name silently return
 * empty/FALSE when the field does not exist or is not a state field, so
 * callers do not need to guard against field-existence errors.
 *
 * Usage example:
 * @code
 * $repo = \Drupal::service(\Drupal\state_machine_ui\Service\WorkflowRepositoryInterface::class);
 *
 * // Can the node transition to "published"?
 * $can = $repo->canTransitionTo($node, 'field_status', 'published');
 *
 * // … AND must the target state have audience=internal?
 * $can = $repo->canTransitionToWithMetadata(
 *   $node, 'field_status', 'published',
 *   ['audience' => ['internal']],
 * );
 *
 * // Which states carry audience=internal in the article_publishing workflow?
 * $states = $repo->getStatesByMetadata('article_publishing', 'audience', 'internal');
 * @endcode
 *
 * @api
 */
interface WorkflowRepositoryInterface extends
  WorkflowFieldAccessInterface,
  TransitionCheckerInterface,
  WorkflowMetadataQueryInterface {

}
