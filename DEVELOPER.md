# State Machine UI — Developer Documentation

## Overview

`state_machine_ui` is a Drupal 11 custom module that provides a full admin UI on top of the [`state_machine`](https://www.drupal.org/project/state_machine) contrib module. It lets site builders create and manage **workflow groups**, **workflows**, **metadata schemas** and **transitions** entirely through the Drupal admin interface — no YAML files required — while remaining fully compatible with YAML-declared workflows from other modules.

**Compatibility:** Drupal `^11.1`, PHP `>=8.3`. Hook implementations use `#[Hook]` attributes declared directly on methods in the Hook classes (introduced in Drupal 11.1). `hook_requirements` is the single exception: Drupal's hook collector refuses to register it as an OO hook, so it stays procedural in `state_machine_ui.install` and delegates to the Hook service. The module ships with **no `.module` file** — it is intentionally omitted because nothing procedural is required at runtime.

---

## Architecture: data model

```
WorkflowGroupConfig          WorkflowMetadataSchema
  id (machine name)            id (machine name)
  label                        label
  entity_type                  field_definitions[]
       │                            key (machine name)
       │                            label
       │                            type (string|list|boolean|number)
       │                            description
       │                       ▲
       │                       │ (optional reference by ID)
       ▼                       │
WorkflowStateMachine ──────────┘
  id (machine name)
  label
  description
  group → WorkflowGroupConfig.id
  default_state
  state_metadata_schema → WorkflowMetadataSchema.id (optional)
  transition_metadata_schema → WorkflowMetadataSchema.id (optional)
  states[]
    key, label, description, weight
    fields{}   ← values for each state_metadata_schema field key

WorkflowTransition
  id ({workflow_id}__{key})
  workflow → WorkflowStateMachine.id
  key, label
  from[], to
  weight
  fields{}   ← values for each transition_metadata_schema field key
```

All four are **config entities** — stored in `config/`, versioned alongside code, deployable via `drush cim/cex`.

---

## Config entity: WorkflowGroupConfig

**Class:** `src/Entity/WorkflowGroupConfig.php`  
**Config prefix:** `state_machine_ui.workflow_group`  
**Admin route:** `/admin/config/workflow/state-machine/groups`

Represents a State Machine `workflow_group` plugin. Injected at runtime via `hook_workflow_groups_alter()` so that the State Machine module recognises them as plugin definitions.

| Property | Type | Description |
|---|---|---|
| `id` | string | Machine name |
| `label` | string | Label |
| `entity_type` | string | Entity type ID this group binds to (e.g. `node`) |

---

## Config entity: WorkflowMetadataSchema

**Class:** `src/Entity/WorkflowMetadataSchema.php`  
**Config prefix:** `state_machine_ui.metadata_schema`  
**Admin route:** `/admin/config/workflow/state-machine/metadata-schemas`

A reusable schema that defines which custom metadata fields a workflow's states can carry. One schema can be referenced by multiple `WorkflowStateMachine` entities — separating the schema structure from the workflow instance.

| Property | Type | Description |
|---|---|---|
| `id` | string | Machine name |
| `label` | string | Label |
| `field_definitions` | array | Ordered list of field definition maps |

Each `field_definition` entry:

```yaml
key: tags          # machine name — immutable once set
label: Tags        # label
type: list         # string | list | boolean | number  (see FieldType enum)
description: '...' # optional help text
```

The `type` is defined by `src/ValueObject/FieldType.php`, a PHP 8.1 backed enum.

---

## Config entity: WorkflowStateMachine

**Class:** `src/Entity/WorkflowStateMachine.php`  
**Config prefix:** `state_machine_ui.workflow`  
**Admin route:** `/admin/config/workflow/state-machine`

Represents a full State Machine workflow plugin. Injected at runtime via `hook_workflows_alter()`.

| Property | Type | Description |
|---|---|---|
| `id` | string | Machine name — must be unique across all State Machine workflow plugins |
| `label` | string | Label |
| `description` | string | Optional free text |
| `group` | string | References a `WorkflowGroupConfig.id` |
| `default_state` | string | State key used as initial value for new entities |
| `state_metadata_schema` | string | References a `WorkflowMetadataSchema.id` for state metadata (optional, `''` = none) |
| `transition_metadata_schema` | string | References a `WorkflowMetadataSchema.id` for transition metadata (optional, `''` = none) |
| `states` | array | Ordered list of state maps |

States are stored as an indexed array (not keyed) with explicit `weight` integers to preserve admin-set ordering. `key` is the machine name used in State Machine plugin registration.

Transitions of this workflow are stored as separate `WorkflowTransition` config entities, related by a `workflow` reference. See below.

Each state's `fields` map contains the metadata values for that state, structured as:

```yaml
fields:
  tags: [editorial, featured]   # list → array of values
  published: true               # boolean → scalar
  score: 42                     # number → scalar
```

---

## Config entity: WorkflowTransition

**Class:** `src/Entity/WorkflowTransition.php`
**Config prefix:** `state_machine_ui.transition`
**Admin route:** `/admin/config/workflow/state-machine/{workflow_state_machine}/transitions`

Represents a single transition of a `WorkflowStateMachine`. Extracted from the workflow entity so transitions can be managed and audited independently.

| Property | Type | Description |
|---|---|---|
| `id` | string | Composite: `{workflow_id}__{key}` |
| `workflow` | string | References `WorkflowStateMachine.id` |
| `key` | string | Transition machine name, scoped to the parent workflow |
| `label` | string | Label |
| `from` | string[] | Allowed origin state keys |
| `to` | string | Target state key |
| `weight` | int | Sort order in `WorkflowTransitionListBuilder` |
| `fields` | array | Custom metadata values keyed by the parent workflow's transition schema (optional) |

Cross-entity integrity:
- `preSave()` validates that `from[]` and `to` reference existing states on the parent workflow, throwing `ConfigValueException` otherwise.
- `calculateDependencies()` declares a config dependency on the parent workflow so deletion cascades automatically.
- `WorkflowForm::validateForm()` blocks removal of a state still referenced by any transition.
- `postSave()` and `postDelete()` invalidate the workflow plugin cache to keep `hook_workflows_alter()` results fresh.

Reads should go through `WorkflowTransitionRepositoryInterface` (per-request memoization). Direct `loadByProperties()` calls are discouraged.

---

## Hook implementation pattern

Most hooks are declared via `#[Hook]` attributes on methods inside Hook classes in `src/Hook/`. There is **no `.module` file** — only the `.install` carries a procedural `hook_requirements` stub.

| Implementation | Hook | Mechanism |
|---|---|---|
| `StateMachineUiHooks::workflowGroupsAlter()` | `hook_workflow_groups_alter` | `#[Hook]` |
| `StateMachineUiHooks::workflowsAlter()` | `hook_workflows_alter` (reads transitions via `WorkflowTransitionRepositoryInterface`) | `#[Hook]` |
| `FormHooks::formAlter()` | `hook_form_alter` | `#[Hook]` |
| `state_machine_ui_requirements()` (.install) → `StateMachineUiHooks::requirements()` | `hook_requirements` | Procedural delegation (OO not allowed by core) |

---

## Forms

### WorkflowGroupForm

`src/Form/WorkflowGroupForm.php` — Standard `EntityForm` for group CRUD. Lists all fieldable entity types in a sorted select element.

### WorkflowMetadataSchemaForm

`src/Form/WorkflowMetadataSchemaForm.php` — `EntityForm` with an AJAX-driven table of field definitions.

**AJAX pattern:**

- Form state key `field_definitions` stores the current list between AJAX calls.
- "Add field" → `addDefSubmit()` appends an empty entry and calls `setRebuild()`.
- "Remove" → `removeDefSubmit()` identifies the row by the triggering button's `#name` (`remove_def_{index}`), splices it out, re-indexes.
- `syncFieldDefs()` reads raw POST input on every submit/AJAX call to persist values typed so far.

**Machine name fields in table rows:**

- **New row** (no key yet): renders `#type: 'machine_name'` with `source` pointing to the sibling label cell. The source element ID is auto-generated by Drupal from the element's parent path — the `source` array must match this path exactly. Do **not** override `#attributes['id']` on the label element; doing so breaks the JS source resolution.
- **Existing row** (key already set): renders a plain `#type: 'textfield'` with `#disabled: TRUE`. Using `machine_name` here would cause the core JS to inject a "Machine name: xxx [Edit]" preview below the **label** field (the source), breaking the table column layout.
- The `syncFieldDefs()` fallback `$key = $raw_def['key'] ?? ($existing[$index]['key'] ?? '')` handles disabled fields absent from POST.

### WorkflowForm

`src/Form/WorkflowForm.php` — The main workflow editor. Four sections:

1. **Metadata schema selector** — select element with `event: change` AJAX that refreshes the states section so metadata sub-forms reflect the new schema.
2. **States section** — draggable table (tabledrag). Each row has label, machine name, description, weight, and an "Edit metadata" toggle that opens an inline sub-form for the state's `fields` values.
3. **Transitions pane** — read-only summary plus a link to the dedicated transitions page (transitions live on their own route).
4. **Diagram preview** — Mermaid `stateDiagram-v2` generated from current form_state states plus the persisted transitions loaded via `WorkflowTransitionRepositoryInterface`.

`validateForm()` blocks the save when an edited workflow drops a state that is still referenced by one or more transitions (queried through `WorkflowTransitionRepositoryInterface::findReferencingStates()`). The error message lists the offending transitions and points the user to edit or delete them first.

**AJAX sync strategy:**

`syncAll()` runs before every AJAX rebuild and before `save()`. It delegates to `syncStates()`. It reads from `getUserInput()` (raw POST) rather than `getValues()` because `getValues()` is not populated during AJAX calls that have `#limit_validation_errors => []`.

**Schema-change AJAX correctness:**

`resolveSchemaFieldDefs()` reads the schema ID from `getUserInput()` first, before `syncAll()` has run. This means AJAX rebuilds triggered by changing the schema select correctly load field definitions from the *new* schema, not the previously stored one.

**Machine name fields** follow the same two-element-type pattern as `WorkflowMetadataSchemaForm` (see above).

### WorkflowTransitionForm

`src/Form/WorkflowTransitionForm.php` — Add/edit form for a single transition. Extends `ConfigEntityFormBase`.

- Parent workflow resolved in `resolveWorkflow()`: on edit from `$entity->getWorkflowId()`, on add from the `workflow_state_machine` route parameter.
- Composite entity ID (`{workflow}__{key}`) is built in `copyFormValuesToEntity()` via `WorkflowTransition::buildId()` — the user only ever sees the unprefixed key.
- The `#machine_name` "exists" callback (`transitionKeyExists()`) checks collisions within the current workflow only; cross-workflow collisions are allowed by design.
- Early return with a clear message when the parent workflow has no states (the From/To selectors would otherwise be empty `required` fields).
- `validateForm()` enforces non-empty `from[]` and `to`; full referential integrity is enforced one layer below by `WorkflowTransition::preSave()`.

### WorkflowTransitionListBuilder

`src/ListBuilder/WorkflowTransitionListBuilder.php` — Draggable list builder. Always rendered through `renderForWorkflow($workflow)` so the listing is scoped; an unscoped `render()` call returns an empty list by design. The dedicated controller `Controller/WorkflowTransitionListController` wires the route to the scoped builder and exposes the page title `"Transitions of {workflow}"`.

---

## Services

All services are declared in `state_machine_ui.services.yml` with `_defaults: autowire: true`. Services with non-standard argument IDs (e.g. `plugin.manager.workflow`) are wired explicitly.

| Service ID | Class | Responsibility |
|---|---|---|
| `state_machine_ui.workflow_repository` | `WorkflowRepository` | Read facade for workflow plugin + state field item (current state, allowed transitions) |
| `state_machine_ui.workflow_transition_repository` | `WorkflowTransitionRepository` | Read facade for `WorkflowTransition` entities; memoizes `loadByWorkflow()` per request |
| `state_machine_ui.workflow_metadata_reader` | `WorkflowMetadataReader` | Reads and caches metadata per request |
| `state_machine_ui.metadata_filter` | `MetadataFilter` | Filters states/transitions by metadata criteria |
| `state_machine_ui.mermaid_builder` | `MermaidDiagramBuilder` | Generates Mermaid markup from workflow data |
| `state_machine_ui.mermaid_locator` | `MermaidLibraryLocator` | Detects if `libraries/mermaid/dist/mermaid.min.js` exists |
| `state_machine_ui.default_state_resolver` | `DefaultStateResolver` | Resolves initial state for new entities |
| `state_machine_ui.conditional_field_resolver` | `ConditionalFieldResolver` | Converts condition rules to Drupal `#states` arrays |
| `state_machine_ui.conditional_required_validator` | `ConditionalRequiredValidator` | Server-side required field validation |
| `state_machine_ui.conditions_table_builder` | `ConditionsTableBuilder` | Builds the AJAX conditions table in widget settings |
| `state_machine_ui.states_table_builder` | `StatesTableBuilder` | Renders the states table inside the workflow form |

### WorkflowMetadataReader

Reads metadata in two modes depending on how the workflow was declared:

**Entity-managed workflows** (stored as `WorkflowStateMachine` config entities):
1. Loads the `WorkflowStateMachine` entity by workflow ID.
2. Resolves the referenced `WorkflowMetadataSchema` to get `field_definitions`.
3. Reads `states[].fields` values, normalising scalars to `string[]`.
4. Logs a warning for any value not matching `[a-z0-9_]+`.

**YAML-declared workflows** (no entity found):
1. Creates the workflow plugin via `WorkflowManagerInterface::createInstance()`.
2. Reads all non-reserved keys from state/transition definitions as metadata.

Results are memoised in `$cache[workflow_id]` for the duration of the request.

### MetadataFilter

Intra-key logic is AND: an item must carry **all** required values for a given key.  
Inter-key logic is configurable AND/OR via the widget setting `filter_logic`.

---

## Field widget: StateFieldRulesWidget

**Plugin ID:** `state_field_rules`  
**Field type:** `state` (from `state_machine`)

Extends the default state field widget with:

- **Conditional field rules** — configured in widget settings via `ConditionsTableBuilder`. Rules hide/show other fields on the form depending on the selected target state. Server-side required validation is enforced via `ConditionalRequiredValidator` registered as `#element_validate`.
- **Metadata filtering** — filters which target states and transitions appear in the select based on the metadata configured on `WorkflowMetadataSchema`.
- **Default state resolution** — `DefaultStateResolver` fills the state field for new entities in the correct order: explicit `default_state` → lowest weight state → first plugin state.
- **Mermaid diagram** — optional partial diagram (current state → allowed transitions) rendered when the library is installed and the user has `view state machine diagram` permission.

---

## Install and update hooks

All hooks live in `state_machine_ui.install`. No `update_N()` hooks are shipped with the initial release — they will be added when a future schema change requires a migration.

| Hook | What it does |
|---|---|
| `hook_install()` | Migrates existing YAML-declared groups, workflows and transitions into config entities. YAML still takes priority — remove the YAML files to use the UI-managed versions. |
| `hook_requirements()` | Delegates to `StateMachineUiHooks::requirements()` for the Mermaid.js status check on `/admin/reports/status`. Stays procedural because Drupal's hook collector refuses to register `hook_requirements` as an OO hook. |
| `hook_uninstall()` | Clears the workflow plugin caches; Drupal removes the entities via config dependencies. |

---

## Config schema

`config/schema/state_machine_ui.schema.yml` defines:

- `state_machine_ui.workflow_group.*`
- `state_machine_ui.metadata_schema.*`
- `state_machine_ui.workflow.*`
- `state_machine_ui.transition.*`
- `field.widget.settings.state_field_rules` (widget settings stored in entity form display config)

---

## Mermaid.js library

The diagram feature depends on the Mermaid.js library, which is **not** bundled.

**Install:**

```bash
mkdir -p web/libraries/mermaid/dist
curl -L https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js \
  -o web/libraries/mermaid/dist/mermaid.min.js
```

`MermaidLibraryLocator` checks for `DRUPAL_ROOT/libraries/mermaid/dist/mermaid.min.js` at runtime. `hook_requirements()` surfaces installation status on `/admin/reports/status`.

---

## Permissions

| Permission | Description |
|---|---|
| `administer state machine workflows` | Full CRUD on groups, workflows, and schemas. |
| `view state machine diagram` | See the Mermaid diagram on entity edit forms. |

---

## Example: Article publishing workflow

The `config/optional/` directory ships example files that install automatically with the module:

| File | What it creates |
|---|---|
| `state_machine_ui.workflow_group.content.yml` | A "Content" group bound to the `node` entity type |
| `state_machine_ui.metadata_schema.article_status.yml` | A schema with `audience` (list) and `section` (list) fields |
| `state_machine_ui.workflow.article_publishing.yml` | A 4-state editorial workflow: `draft → review → published → archived` |
| `state_machine_ui.transition.article_publishing__*.yml` (×5) | The five transitions of the workflow (`submit_for_review`, `approve`, `reject`, `archive`, `restore`) |

Every transition YAML declares `dependencies.config` pointing to the parent workflow so config import resolves them in the correct order.

---

## Adding a new workflow programmatically

```php
$schema = \Drupal::entityTypeManager()
  ->getStorage('workflow_metadata_schema')
  ->create([
    'id' => 'my_schema',
    'label' => 'My schema',
    'field_definitions' => [
      ['key' => 'category', 'label' => 'Category', 'type' => 'list', 'description' => ''],
    ],
  ]);
$schema->save();

$workflow = \Drupal::entityTypeManager()
  ->getStorage('workflow_state_machine')
  ->create([
    'id' => 'my_workflow',
    'label' => 'My workflow',
    'group' => 'content',
    'default_state' => 'draft',
    'state_metadata_schema' => 'my_schema',
    'transition_metadata_schema' => 'my_schema',
    'states' => [
      ['key' => 'draft',     'label' => 'Draft',     'weight' => 0, 'fields' => ['category' => ['internal']]],
      ['key' => 'published', 'label' => 'Published', 'weight' => 1, 'fields' => ['category' => ['public']]],
    ],
  ]);
$workflow->save();

$transition_storage = \Drupal::entityTypeManager()->getStorage('workflow_transition');
foreach (
  [
    ['key' => 'publish',   'label' => 'Publish',   'from' => ['draft'],     'to' => 'published'],
    ['key' => 'unpublish', 'label' => 'Unpublish', 'from' => ['published'], 'to' => 'draft'],
  ] as $weight => $transition
) {
  $transition_storage->create([
    'id' => \Drupal\state_machine_ui\Entity\WorkflowTransition::buildId('my_workflow', $transition['key']),
    'workflow' => 'my_workflow',
    'key' => $transition['key'],
    'label' => $transition['label'],
    'from' => $transition['from'],
    'to' => $transition['to'],
    'weight' => $weight,
  ])->save();
}
```

---

## Key design decisions

**Why a standalone MetadataSchema entity?**  
Multiple workflows can share the same field structure (e.g. an `audience` + `section` schema used by both article and event workflows). Separating schema from workflow prevents duplication and allows changing field labels/descriptions in one place.

**Why indexed arrays for states?**
Drupal config entities store properties as raw PHP arrays. Using indexed arrays (with explicit `weight` integers) makes admin-set ordering stable across config imports and allows tabledrag reordering without rewriting keys.

**Why are transitions their own config entity instead of an array on the workflow?**
Per-transition operations (edit, delete, reorder, permissions, audit, override via config splits) all become first-class once the transition has its own entity ID and route. Listing and filtering transitions becomes a regular `loadByProperties()` query. The trade-off is referential integrity, handled in `WorkflowTransition::preSave()` and `WorkflowForm::validateForm()`.

**Why two independent metadata schemas (state vs transition)?**
States and transitions describe different things (a *status* vs an *action*), so editorial teams typically want different field sets — e.g. an `audience` tag on states, a `requires_comment` flag on transitions. Sharing a single schema would force one to absorb the other's keys. Two separate references keep them decoupled, while still allowing the same schema to be reused on both sides when that suits.

**Why `#[Hook]` attributes instead of procedural stubs?**
`#[Hook]` attributes (introduced in Drupal 11.1, which is the module's minimum requirement) allow hook implementations to live inside typed, injectable service classes rather than in the global namespace. This keeps the code testable and avoids the boilerplate of procedural stubs that do nothing but delegate. `hook_requirements()` is the single carve-out — Drupal's hook collector refuses to register it as an OO hook, so it stays procedural in `state_machine_ui.install`.

**Why are metadata values validated against `[a-z0-9_]+`?**  
The values are used as CSS classes, filter keys, and URL parameters in downstream code. Restricting them to lowercase alphanumeric + underscore avoids encoding issues across all contexts.

**Why two element types for machine_name in table rows?**  
Drupal's `machine_name` JS always injects a "Machine name: [value]" preview **below the source field** (the label), not below itself. In a table layout this breaks the column structure. For new rows the auto-populate JS is desirable. For existing rows (locked key) a plain disabled `textfield` is used instead — no `drupalSettings.machineName` is emitted, so no JS runs on the label cell.
