<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Workflow config entity.
 *
 * Exposed as a State Machine workflow plugin via hook_workflows_alter().
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
 *     "entity_bindings",
 *     "field_definitions",
 *     "states",
 *     "transitions",
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

  protected string $id = '';
  protected string $label = '';
  protected string $description = '';
  protected string $group = '';

  /** @var array<int, array{entity_type: string, bundle: string, field_name: string}> */
  protected array $entity_bindings = [];

  /** @var array<int, array{key: string, label: string, type: string, description: string}> */
  protected array $field_definitions = [];

  /** @var array<int, array<string, mixed>> */
  protected array $states = [];

  /** @var array<int, array{key: string, label: string, from: string[], to: string}> */
  protected array $transitions = [];

}
