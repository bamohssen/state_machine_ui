<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine_ui\Entity\WorkflowGroupConfig;

/**
 * Form for Workflow Group config entities.
 */
final class WorkflowGroupForm extends ConfigEntityFormBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function entityNoun(): string {
    return (string) $this->t('group');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\state_machine_ui\Entity\WorkflowGroupConfig $group */
    $group = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group name'),
      '#maxlength' => 255,
      '#default_value' => $group->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $group->id(),
      '#machine_name' => [
        'exists' => WorkflowGroupConfig::class . '::load',
      ],
      '#disabled' => !$group->isNew(),
    ];

    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      if ($definition->entityClassImplements(FieldableEntityInterface::class)) {
        $options[$id] = (string) $definition->getLabel();
      }
    }
    asort($options);

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('The entity type this workflow group applies to.'),
      '#options' => $options,
      '#default_value' => $group->getWorkflowEntityType(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function save(array $form, FormStateInterface $form_state): int {
    return $this->saveAndRedirect($form, $form_state);
  }

}
