# State Machine UI

Administration UI and enhanced widget for the [State Machine](https://www.drupal.org/project/state_machine) module (Commerce Guys).

## What this module does

### 1. Manage workflows via the admin UI

Create and edit State Machine workflow groups, workflows, states and transitions directly from the Drupal admin interface — no YAML files needed.

- **Admin > Configuration > Workflow > State Machine Workflows** — three tabs: Workflows, Metadata schemas, Workflow groups
- Workflow groups bind a set of workflows to an entity type
- **Metadata schemas** declare reusable custom metadata fields (e.g. `tags`, `category`)
- Workflows live in a group and optionally reference up to two metadata schemas — one for **state** metadata and one for **transition** metadata (independent)
- **Transitions** are managed on their own page, scoped to a workflow (tab "Transitions" on the workflow edit page); each transition can carry its own metadata values when the workflow declares a transition schema
- Workflow groups, workflows, metadata schemas and transitions are stored as Config Entities and injected into State Machine via `hook_workflows_alter()`

### 2. Enhanced widget for state fields

A custom widget **"State with rules"** (`state_field_rules`) that replaces the default `options_select` for `state` fields. Select it in **Manage Form Display**.

Features:
- **Current state** displayed as read-only text
- **Target state select** — shows allowed target states (deduplicated from transitions), not transition labels
- **No transition behavior** — configurable: hide the select or show a custom message when no transitions are available
- **Conditional field rules** — Each rule declares a visibility (show/hide). Default is show. If a field has both show and hide rules across different states, show wins: the field behaves as a whitelist and is visible only in its declared show states. A field marked required is only enforced server-side when it is effectively visible for the selected target state.
- **Server-side validation** — required fields are validated using `FieldItemList::isEmpty()`, works correctly for all field types

### 3. Metadata filtering

Filter which states and transitions appear in the widget based on custom metadata declared in the workflow.

- Metadata schema defines which keys exist (e.g. `tags`, `reason`)
- State values are stored on `WorkflowStateMachine.states[].fields`; transition values on `WorkflowTransition.fields`
- Detects metadata keys dynamically (no hardcoded keys)
- Admin configures filters via checkboxes in Manage Form Display
- Intra-key logic: AND (state must have ALL checked values)
- Inter-key logic: configurable AND/OR
- Pure UX filter — no server-side guard, existing State Machine guards handle security

### 4. Mermaid.js workflow diagram

Optionally display an interactive state diagram on entity forms.

- Requires [Mermaid.js](https://mermaid.js.org/) installed in `libraries/mermaid/dist/mermaid.min.js`
- Controlled by widget setting + `view state machine diagram` permission
- Rendered in a collapsible `<details>` element
- `<pre>` fallback if JS fails
- XSS-safe: uses `DOMParser` + `replaceChildren()`, `securityLevel: 'strict'`

## Requirements

- Drupal 11.1+ (the module uses OO `#[Hook]` attributes, introduced in 11.1)
- PHP 8.3+
- [State Machine](https://www.drupal.org/project/state_machine) module
- Mermaid.js library (optional, for diagrams only)

## Installation

1. Place the module in `modules/custom/` (or install via Composer once available on drupal.org)
2. Enable: `drush en state_machine_ui`
3. On installation, existing YAML-declared workflows are automatically migrated to Config Entities

**Important:** After installation, the YAML files still take priority over Config Entities (State Machine's `YamlDiscovery` runs first). To use the UI-managed versions, **delete your `*.workflows.yml` and `*.workflow_groups.yml` files** and clear caches.

### Installing Mermaid.js (optional)

Mermaid.js powers the workflow diagram feature only. The rest of the
module — admin UI, conditional rules, metadata filtering, transition
history — works without it. Skip this section if you don't need diagrams.

> **Why no Composer install?**
> Mermaid 10/11 pulls in 50+ transitive npm packages with version
> conflicts on `d3-path`/`cose-base` that Composer cannot resolve (npm
> allows multiple versions of the same package in a single tree,
> Composer does not). asset-packagist therefore is not viable for
> Mermaid — manual install is the only reliable path.

#### Drop the prebuilt file into `libraries/`

1. Download the latest prebuilt file from the Mermaid project, e.g.:

   ```bash
   mkdir -p web/libraries/mermaid/dist
   curl -fSL https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js \
     -o web/libraries/mermaid/dist/mermaid.min.js
   ```

2. Clear the Drupal cache:

   ```bash
   drush cr
   ```

**Verify:** Go to `/admin/reports/status` — when the library is present
you will see "State Machine UI — Mermaid.js: Installed". When absent the
status is purely informational; the module continues to work, only the
diagram view is hidden.

## Quick start

### Creating a workflow from scratch

1. Go to **Admin > Configuration > Workflow > State Machine Workflows**
2. Create a **Workflow Group** first (e.g. "Publication", linked to entity type "Content")
3. *(Optional)* Create a **Metadata Schema** under **Metadata schemas** — define the field keys (e.g. `tags`, `category`) that states can carry
4. Create a **Workflow** in that group
   - Optionally select a **State metadata schema** and/or a **Transition metadata schema** (independent)
   - Add **states** — each needs a label and a machine name; click "Edit metadata" on a state to assign values
5. Save the workflow
6. Open the **Transitions** tab on the workflow edit page and add transitions
   - Each transition has a label, machine name, one or more "From" states, and a single "To" state
   - If a transition metadata schema is set, the transition form exposes one textarea per declared field
   - Transitions reorder via drag-and-drop; the order is honoured by the State Machine plugin system

### Using the widget

1. Add a `state` field to your content type (provided by State Machine)
2. In **Manage Form Display**, change the widget to **"State with rules"**
3. Click the gear icon to configure:
   - **Is required** — control whether the selector is required at form level
   - **Option labels** — choose between the target state label or the transition label
   - **Empty option** — start the selector unselected and force an active pick
   - **Transition comment** — optional or required textarea written to the entity's revision log
   - **Check per-transition permission** — filter transitions by the dynamic `use {workflow} {transition} transition` permission
   - **No transition behavior** — hide the selector or show a custom message
   - **Conditional field rules** — show/require other fields based on the selected target state
   - **Metadata filters** — when the workflow has metadata schemas with values
   - **Workflow diagram** — toggle the Mermaid preview
   - **Transition history** — show a table of past state changes (set `-1` to display every recorded transition)

## Architecture

```
state_machine_ui/
├── src/
│   ├── Access/                          — WorkflowPermissions (dynamic per-transition permissions)
│   ├── Constant/                        — Enums (FieldType, FilterLogic, Visibility, …) + shared constants
│   ├── Constraint/                      — MetadataValueConstraint
│   ├── Controller/                      — Scoped transitions list page
│   ├── Entity/                          — Config Entities (WorkflowGroup, Workflow, WorkflowMetadataSchema, WorkflowTransition)
│   ├── Form/                            — Entity forms + ConditionsTableBuilder + StatesTableBuilder
│   ├── Hook/                            — Service classes wired via #[Hook] attributes
│   ├── ListBuilder/                     — Admin listing pages
│   ├── Plugin/Field/FieldWidget/        — StateFieldRulesWidget
│   ├── Service/                         — Business logic services
│   └── Validation/                      — Server-side conditional validation
├── js/mermaid-render.js                 — Mermaid rendering behavior
├── config/schema/                       — Config schema
└── state_machine_ui.install             — YAML migration on install + hook_requirements
```

### Data model

```
WorkflowGroupConfig     1 ─→ N  WorkflowStateMachine
WorkflowMetadataSchema  1 ─→ N  WorkflowStateMachine  (state_metadata_schema, optional)
WorkflowMetadataSchema  1 ─→ N  WorkflowStateMachine  (transition_metadata_schema, optional)
WorkflowStateMachine    1 ─→ N  WorkflowTransition
```

A **WorkflowMetadataSchema** defines which metadata keys exist and their types (string, list, boolean, number). It is reusable across multiple workflows and across both states and transitions; a workflow with no schema simply has no metadata.

The workflow carries two independent references:
- `state_metadata_schema` — applied to each state's `WorkflowStateMachine.states[].fields` map
- `transition_metadata_schema` — applied to each transition's `WorkflowTransition.fields` map

A **WorkflowTransition** lives in its own config entity, related to its parent workflow by a `workflow` reference. Its global entity ID is the composite `{workflow_id}__{key}` so the same key (e.g. `publish`) can be reused across workflows.

### Key services

| Service | Responsibility |
|---------|---------------|
| `WorkflowTransitionRepository` | Read-side facade for transitions, scoped to a workflow, with per-request memoization |
| `WorkflowRepository` | Read-side facade for the workflow plugin (state, transitions, metadata) attached to an entity field |
| `TransitionAccessChecker` | Filters transitions through dynamic per-transition permissions |
| `TransitionOptionsBuilder` | Builds the deduplicated `{state_id => label}` option list shown by the widget selector |
| `TransitionHistoryProvider` / `RevisionTransitionHistoryProvider` | Returns past transitions for the widget history table, by scanning entity revisions |
| `WorkflowMetadataReader` | Reads metadata: field defs from `WorkflowMetadataSchema`, values from both state and transition fields. Falls back to plugin definition for YAML-declared workflows |
| `MetadataFilter` | Applies AND/OR filtering logic on states/transitions |
| `ConditionalFieldResolver` | Converts widget rules into Drupal `#states` arrays |
| `ConditionalRequiredValidator` | Server-side validation for conditionally required fields |
| `MermaidDiagramBuilder` | Generates Mermaid stateDiagram-v2 markup |
| `MermaidLibraryLocator` | Detects if Mermaid.js is installed locally |

## Metadata filtering — how it works

1. Create a **Metadata Schema** with keys `tag` and `category`
2. Assign values to each state in the workflow (e.g. state `draft` has `tag: [can_be_edited]`)
3. In the widget settings, metadata filter checkboxes appear grouped by key
4. When filters are configured, only states/transitions matching the filter are shown

The module detects available metadata keys from the schema automatically. Reserved keys (`label`, `from`, `to`) are never exposed.

## Permissions

| Permission | Description |
|-----------|-------------|
| `administer state machine workflows` | Create, edit, delete workflow groups, workflows, metadata schemas and transitions |
| `view state machine diagram` | See the Mermaid diagram on entity forms |
| `use {workflow} {transition} transition` | Dynamic, one per workflow × transition pair. Generated by `WorkflowPermissions` and consumed by `TransitionAccessChecker` when the widget setting "Check per-transition permission" is on |

## Compatibility

- No modifications to the `state_machine` module
