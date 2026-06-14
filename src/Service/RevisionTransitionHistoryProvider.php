<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds transition history by scanning entity revisions.
 *
 * Generic implementation: works for any RevisionableInterface +
 * RevisionLogInterface entity whose state field exists on every revision.
 * No dependency on the optional state_machine_history submodule.
 *
 * @internal
 */
final class RevisionTransitionHistoryProvider implements TransitionHistoryProviderInterface {

  /**
   * Constructs a RevisionTransitionHistoryProvider instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Used to obtain the revisionable storage handler at runtime.
   */
  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   *
   * A limit of -1 (or any negative value) means "unlimited"; 0 returns no
   * history at all.
   */
  #[\Override]
  public function getHistory(FieldableEntityInterface $entity, string $field_name, int $limit): array {
    if ($limit === 0) {
      return [];
    }
    if (!$entity instanceof RevisionableInterface || $entity->id() === NULL) {
      return [];
    }
    if (!$entity->hasField($field_name)) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if (!$storage instanceof RevisionableStorageInterface) {
      return [];
    }

    $revisions = $this->loadRecentRevisions($entity, $storage, $limit);
    return $this->extractTransitions($revisions, $field_name, $limit);
  }

  /**
   * Loads at most ($limit + 1) revisions, newest first, for one entity.
   *
   * Loading one extra revision is intentional: it provides the "previous
   * state" baseline for the oldest visible transition.
   *
   * The entity's static cache is reset before loading. Otherwise the
   * current default revision would be returned with a freshly cleared
   * revision log message: `ContentEntityForm::form()` empties it during
   * build to prefill the "Revision log message" textarea, and that
   * mutation persists in the static cache.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   The entity whose revisions should be loaded.
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $storage
   *   The matching storage handler.
   * @param int $limit
   *   The widget-configured limit; values <= 0 fetch every revision.
   *
   * @return array<int, \Drupal\Core\Entity\FieldableEntityInterface>
   *   Loaded revisions, newest first.
   */
  private function loadRecentRevisions(RevisionableInterface $entity, RevisionableStorageInterface $storage, int $limit): array {
    $type = $entity->getEntityType();
    $revision_query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($type->getKey('id'), $entity->id())
      ->sort($type->getKey('revision'), 'DESC');
    if ($limit > 0) {
      $revision_query->range(0, $limit + 1);
    }
    $revision_ids = array_keys($revision_query->execute());

    $storage->resetCache([$entity->id()]);

    $revisions = [];
    foreach ($revision_ids as $vid) {
      $revision = $storage->loadRevision((int) $vid);
      if ($revision instanceof FieldableEntityInterface) {
        $revisions[] = $revision;
      }
    }
    return $revisions;
  }

  /**
   * Walks revisions newest-first and yields one entry per actual state change.
   *
   * The oldest revision is reported as an "initial state set" entry
   * (from = ''), so an entity that has never transitioned still shows the
   * state it was created with.
   *
   * @param array<int, \Drupal\Core\Entity\FieldableEntityInterface> $revisions
   *   Revisions newest-first.
   * @param string $field_name
   *   The state field machine name.
   * @param int $limit
   *   Maximum number of entries to return; values <= 0 return all.
   *
   * @return array<int, array{from: string, to: string, uid: int, timestamp: int, comment: string}>
   *   The transition entries.
   */
  private function extractTransitions(array $revisions, string $field_name, int $limit): array {
    $entries = [];
    foreach ($revisions as $index => $revision) {
      $previous = $revisions[$index + 1] ?? NULL;
      $to = $this->readStateValue($revision, $field_name);
      $from = $previous !== NULL ? $this->readStateValue($previous, $field_name) : '';

      if ($to === '' || $to === $from) {
        continue;
      }

      $entries[] = [
        'from' => $from,
        'to' => $to,
        'uid' => $revision instanceof RevisionLogInterface ? (int) $revision->getRevisionUserId() : 0,
        'timestamp' => $revision instanceof RevisionLogInterface ? (int) $revision->getRevisionCreationTime() : 0,
        'comment' => $revision instanceof RevisionLogInterface ? (string) ($revision->getRevisionLogMessage() ?? '') : '',
      ];
      if ($limit > 0 && count($entries) >= $limit) {
        break;
      }
    }
    return $entries;
  }

  /**
   * Returns the state field value of a revision, or '' when unset.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $revision
   *   The revision to inspect.
   * @param string $field_name
   *   The state field machine name.
   */
  private function readStateValue(FieldableEntityInterface $revision, string $field_name): string {
    if (!$revision->hasField($field_name) || $revision->get($field_name)->isEmpty()) {
      return '';
    }
    return (string) $revision->get($field_name)->value;
  }

}
