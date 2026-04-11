<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Workflow Group config entity.
 *
 * Exposed as a State Machine workflow_group plugin via hook_workflow_groups_alter().
 *
 * @ConfigEntityType(
 *   id = "workflow_group_config",
 *   label = @Translation("Workflow Group"),
 *   label_collection = @Translation("Workflow Groups"),
 *   handlers = {
 *     "list_builder" = "Drupal\state_machine_ui\ListBuilder\WorkflowGroupListBuilder",
 *     "form" = {
 *       "add" = "Drupal\state_machine_ui\Form\WorkflowGroupForm",
 *       "edit" = "Drupal\state_machine_ui\Form\WorkflowGroupForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "workflow_group",
 *   admin_permission = "administer state machine workflows",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "entity_type",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/state-machine/groups",
 *     "add-form" = "/admin/config/workflow/state-machine/groups/add",
 *     "edit-form" = "/admin/config/workflow/state-machine/groups/{workflow_group_config}",
 *     "delete-form" = "/admin/config/workflow/state-machine/groups/{workflow_group_config}/delete",
 *   },
 * )
 */
class WorkflowGroupConfig extends ConfigEntityBase {

  protected string $id = '';
  protected string $label = '';
  protected string $entity_type = '';

}
