<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for Workflow Group config entities.
 */
class WorkflowGroupForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
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
        'exists' => '\Drupal\state_machine_ui\Entity\WorkflowGroupConfig::load',
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
      '#default_value' => $group->get('entity_type') ?? '',
      '#required' => TRUE,
    ];

    return $form;
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $status = $this->entity->save();
    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Created group %label.', ['%label' => $this->entity->label()])
        : $this->t('Updated group %label.', ['%label' => $this->entity->label()])
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }

}
