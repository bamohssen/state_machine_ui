<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Workflow config entity.
 *
 * Exposed as a State Machine workflow plugin via hook_workflows_alter().
 * State values are stored in each state's `fields` map; the allowed keys
 * are governed by the referenced WorkflowMetadataSchema (if any).
 * Transitions live in their own WorkflowTransition config entity, related
 * by a workflow ID reference.
 *
 * @ConfigEntityType(
 *   id = "workflow_state_machine",
 *   label = @Translation("State Machine Workflow"),
 *   label_collection = @Translation("State Machine Workflows"),
 *   handlers = {
 *     "list_builder" = "Drupal\state_machine_ui\ListBuilder\WorkflowListBuilder",
 *     "form" = {
 *       "add" = "Drupal\state_machine_ui\Form\WorkflowForm",
 *       "edit" = "Drupal\state_machine_ui\Form\WorkflowForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "workflow",
 *   admin_permission = "administer state machine workflows",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "group",
 *     "default_state",
 *     "state_metadata_schema",
 *     "transition_metadata_schema",
 *     "states",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/state-machine",
 *     "add-form" = "/admin/config/workflow/state-machine/add",
 *     "edit-form" = "/admin/config/workflow/state-machine/{workflow_state_machine}",
 *     "delete-form" = "/admin/config/workflow/state-machine/{workflow_state_machine}/delete",
 *   },
 * )
 */
class WorkflowStateMachine extends ConfigEntityBase {

  /**
   * Machine name.
   */
  protected string $id = '';

  /**
   * Label.
   */
  protected string $label = '';

  /**
   * Optional free-text description.
   */
  protected string $description = '';

  /**
   * Parent WorkflowGroupConfig ID.
   */
  protected string $group = '';

  /**
   * Default state key applied to new entities.
   */
  protected string $default_state = '';

  /**
   * Optional WorkflowMetadataSchema ID for state metadata; '' when none.
   */
  protected string $state_metadata_schema = '';

  /**
   * Optional WorkflowMetadataSchema ID for transition metadata; '' when none.
   */
  protected string $transition_metadata_schema = '';

  /**
   * Ordered list of states.
   *
   * Each entry has keys: key, label, description, weight, fields (map).
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $states = [];

  /**
   * Returns the optional description of this workflow's purpose.
   *
   * @return string
   *   The workflow description, or an empty string if none is set.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Returns the workflow group ID.
   *
   * @return string
   *   The machine name of the referenced WorkflowGroupConfig entity.
   */
  public function getGroup(): string {
    return $this->group;
  }

  /**
   * Returns the machine name of the initial state for new entities.
   *
   * @return string
   *   The default state machine name, or empty string if none is declared.
   */
  public function getDefaultState(): string {
    return $this->default_state;
  }

  /**
   * Returns the optional WorkflowMetadataSchema ID for state metadata.
   *
   * @return string
   *   The schema entity ID, or empty string when none is configured.
   */
  public function getStateMetadataSchema(): string {
    return $this->state_metadata_schema;
  }

  /**
   * Returns the optional WorkflowMetadataSchema ID for transition metadata.
   *
   * @return string
   *   The schema entity ID, or empty string when none is configured.
   */
  public function getTransitionMetadataSchema(): string {
    return $this->transition_metadata_schema;
  }

  /**
   * Returns the ordered list of state definitions.
   *
   * @return array<int, array<string, mixed>>
   *   Indexed array of state maps, each with keys: key, label, description,
   *   weight, fields.
   */
  public function getStates(): array {
    return $this->states;
  }

}
