<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Returns a chronological log of state-field transitions for an entity.
 *
 * One entry per *state change*; revisions that did not move the workflow
 * forward are skipped so the history reflects business events rather than
 * every save.
 */
interface TransitionHistoryProviderInterface {

  /**
   * Returns the transition history of an entity for one state field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to inspect.
   * @param string $field_name
   *   The state field machine name.
   * @param int $limit
   *   Maximum number of transitions to return, newest first.
   *
   * @return array<int, array{from: string, to: string, uid: int, timestamp: int, comment: string}>
   *   Transition entries, newest first. Empty when the entity is not
   *   revisionable or has no recorded transitions.
   */
  public function getHistory(FieldableEntityInterface $entity, string $field_name, int $limit): array;

}
