<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Entity;

use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;

/**
 * A transition between two states of a workflow.
 *
 * The entity ID is the composite "{workflow_id}__{key}" because config
 * entity IDs must be globally unique, while the same key (e.g. "publish")
 * may legitimately appear in multiple workflows. UI labels always show
 * the unprefixed key; only the storage layer sees the composite.
 *
 * @ConfigEntityType(
 *   id = "workflow_transition",
 *   label = @Translation("Workflow Transition"),
 *   label_collection = @Translation("Workflow Transitions"),
 *   handlers = {
 *     "list_builder" = "Drupal\state_machine_ui\ListBuilder\WorkflowTransitionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\state_machine_ui\Form\WorkflowTransitionForm",
 *       "edit" = "Drupal\state_machine_ui\Form\WorkflowTransitionForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "transition",
 *   admin_permission = "administer state machine workflows",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "workflow",
 *     "key",
 *     "from",
 *     "to",
 *     "weight",
 *     "fields",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/state-machine/{workflow_state_machine}/transitions",
 *     "add-form" = "/admin/config/workflow/state-machine/{workflow_state_machine}/transitions/add",
 *     "edit-form" = "/admin/config/workflow/state-machine/{workflow_state_machine}/transitions/{workflow_transition}",
 *     "delete-form" = "/admin/config/workflow/state-machine/{workflow_state_machine}/transitions/{workflow_transition}/delete",
 *   },
 * )
 */
class WorkflowTransition extends ConfigEntityBase {

  /**
   * Composite config entity ID: "{workflow_id}__{key}".
   */
  protected string $id = '';

  /**
   * Label.
   */
  protected string $label = '';

  /**
   * Parent WorkflowStateMachine entity ID.
   */
  protected string $workflow = '';

  /**
   * Transition machine name, unique within its parent workflow.
   */
  protected string $key = '';

  /**
   * Allowed origin state keys.
   *
   * @var string[]
   */
  protected array $from = [];

  /**
   * Target state key.
   */
  protected string $to = '';

  /**
   * Sort order within the parent workflow.
   */
  protected int $weight = 0;

  /**
   * Custom metadata values keyed by the parent workflow's schema field key.
   *
   * Same shape as WorkflowStateMachine.states[].fields; raw values, parsed
   * by EntityMetadataParser at read time.
   *
   * @var array<string, mixed>
   */
  protected array $fields = [];

  /**
   * Returns the composite entity ID for a (workflow, key) pair.
   *
   * @param string $workflow_id
   *   The parent workflow ID.
   * @param string $key
   *   The unprefixed transition machine name.
   *
   * @return string
   *   The composite ID used as the config entity ID.
   */
  public static function buildId(string $workflow_id, string $key): string {
    return $workflow_id . StateMachineUiConstants::TRANSITION_ID_SEPARATOR . $key;
  }

  /**
   * Returns the parent WorkflowStateMachine entity ID.
   */
  public function getWorkflowId(): string {
    return $this->workflow;
  }

  /**
   * Returns the unprefixed transition machine name.
   */
  public function getKey(): string {
    return $this->key;
  }

  /**
   * Returns the state keys this transition can be fired from.
   *
   * @return string[]
   *   Origin state keys.
   */
  public function getFromStates(): array {
    return $this->from;
  }

  /**
   * Returns the target state key reached by this transition.
   */
  public function getToState(): string {
    return $this->to;
  }

  /**
   * Returns the transition weight within the parent workflow's listing.
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * Returns the raw metadata values for this transition.
   *
   * @return array<string, mixed>
   *   Field key => raw value (string, list, etc.), keyed by schema field key.
   */
  public function getFields(): array {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   *
   * Adds the parent workflow ID so that link templates that contain
   * {workflow_state_machine} resolve to a usable URL.
   */
  #[\Override]
  protected function urlRouteParameters($rel): array {
    $parameters = parent::urlRouteParameters($rel);
    $parameters['workflow_state_machine'] = $this->workflow;
    return $parameters;
  }

  /**
   * {@inheritdoc}
   *
   * Depends on the parent workflow so transition removal cascades when
   * the workflow is deleted.
   */
  #[\Override]
  public function calculateDependencies(): static {
    parent::calculateDependencies();
    if ($this->workflow !== '') {
      $this->addDependency('config', StateMachineUiConstants::CONFIG_PREFIX_WORKFLOW . $this->workflow);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   *   When the parent workflow is missing, the composite ID is malformed,
   *   or a referenced state is not declared on the workflow.
   */
  #[\Override]
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if ($this->workflow === '') {
      throw new ConfigValueException('A workflow transition must reference a parent workflow.');
    }
    if ($this->key === '') {
      throw new ConfigValueException('A workflow transition must have a non-empty key.');
    }
    if ($this->id !== self::buildId($this->workflow, $this->key)) {
      throw new ConfigValueException(sprintf(
        'Transition ID "%s" does not match its workflow/key pair ("%s" / "%s").',
        $this->id,
        $this->workflow,
        $this->key,
      ));
    }

    $workflow_entity = \Drupal::entityTypeManager()
      ->getStorage(StateMachineUiConstants::ENTITY_WORKFLOW)
      ->load($this->workflow);
    if (!$workflow_entity instanceof WorkflowStateMachine) {
      throw new ConfigValueException(sprintf(
        'Workflow "%s" referenced by transition "%s" does not exist.',
        $this->workflow,
        $this->id,
      ));
    }

    $state_keys = array_filter(array_column($workflow_entity->getStates(), 'key'));
    $invalid = array_values(array_diff(array_merge($this->from, [$this->to]), $state_keys));
    if ($invalid !== []) {
      throw new ConfigValueException(sprintf(
        'Transition "%s" references unknown state(s): %s',
        $this->id,
        implode(', ', $invalid),
      ));
    }
  }

  /**
   * {@inheritdoc}
   *
   * Refreshes the workflow plugin definitions so the State Machine plugin
   * system sees the new transition on the next request.
   */
  #[\Override]
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    self::invalidateWorkflowPluginCache();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);
    self::invalidateWorkflowPluginCache();
  }

  /**
   * Forces the state_machine plugin manager to rebuild its definitions cache.
   *
   * Called after any transition save or delete so that `hook_workflows_alter()`
   * re-runs and the latest transition set is reflected at runtime.
   */
  private static function invalidateWorkflowPluginCache(): void {
    \Drupal::service('plugin.manager.workflow')->clearCachedDefinitions();
  }

}
