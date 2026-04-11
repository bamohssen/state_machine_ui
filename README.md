# State Machine UI

Administration UI and enhanced widget for the [State Machine](https://www.drupal.org/project/state_machine) module (Commerce Guys).

## What this module does

### 1. Manage workflows via the admin UI

Create and edit State Machine workflow groups, states, and transitions directly from the Drupal admin interface — no YAML files needed.

- **Admin > Configuration > Workflow > State Machine Workflows**
- Create workflow groups (linked to an entity type)
- Create workflows with states, transitions, and optional custom metadata fields per state
- Workflows are stored as Config Entities and injected into State Machine via `hook_workflows_alter()`

### 2. Enhanced widget for state fields

A custom widget **"State with rules"** (`state_field_rules`) that replaces the default `options_select` for `state` fields. Select it in **Manage Form Display**.

Features:
- **Current state** displayed as read-only text
- **Target state select** — shows allowed target states (deduplicated from transitions), not transition labels
- **No transition behavior** — configurable: hide the select or show a custom message when no transitions are available
- **Conditional field rules** — show/hide and require other form fields based on the selected target state (blacklist model: referenced fields are hidden by default)
- **Server-side validation** — required fields are validated using `FieldItemList::isEmpty()`, works correctly for all field types

### 3. Metadata filtering

Filter which states and transitions appear in the widget based on custom metadata declared in the workflow YAML.

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

- Drupal 10.3+ or 11.x
- PHP 8.2+
- [State Machine](https://www.drupal.org/project/state_machine) module
- Mermaid.js library (optional, for diagrams only)

## Installation

1. Install the module as usual: `composer require drupal/state_machine_ui` or place it in `modules/custom/`
2. Enable: `drush en state_machine_ui`
3. On installation, existing YAML-declared workflows are automatically migrated to Config Entities

**Important:** After installation, the YAML files still take priority over Config Entities (State Machine's `YamlDiscovery` runs first). To use the UI-managed versions, **delete your `*.workflows.yml` and `*.workflow_groups.yml` files** and clear caches.

### Installing Mermaid.js (optional)

Mermaid.js is required only for workflow diagrams. The module works without it.

**Option 1 — npm (recommended):**

```bash
cd /path/to/drupal
mkdir -p libraries/mermaid/dist
npm pack mermaid --pack-destination /tmp
tar -xzf /tmp/mermaid-*.tgz -C /tmp
cp /tmp/package/dist/mermaid.min.js libraries/mermaid/dist/
rm -rf /tmp/package /tmp/mermaid-*.tgz
```

**Option 2 — direct download:**

1. Go to https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js
2. Save the file to `libraries/mermaid/dist/mermaid.min.js`

**Option 3 — composer (with asset-packagist):**

If your project uses [asset-packagist](https://asset-packagist.org/):

```bash
composer require npm-asset/mermaid
```

Then symlink or copy `vendor/npm-asset/mermaid/dist/mermaid.min.js` to `libraries/mermaid/dist/mermaid.min.js`.

**Verify:** Go to `/admin/reports/status` — you should see "State Machine UI — Mermaid.js: Installed".

## Quick start

### Creating a workflow from scratch

1. Go to **Admin > Configuration > Workflow > State Machine Workflows**
2. Create a **Workflow Group** first (e.g. "Publication", linked to entity type "Content")
3. Create a **Workflow** in that group
4. Add **states** — each needs a label and a machine name. Mark one as "Initial"
5. Add **transitions** — each needs a label, machine name, one or more "From" states, and one "To" state. **Add states first** — the From/To options come from them
6. Save

### Using the widget

1. Add a `state` field to your content type (provided by State Machine)
2. In **Manage Form Display**, change the widget to **"State with rules"**
3. Click the gear icon to configure:
   - No transition behavior
   - Conditional field rules (show/require other fields based on selected state)
   - Metadata filters (if your workflow has custom metadata)
   - Workflow diagram toggle

## Architecture

```
state_machine_ui/
├── src/
│   ├── Entity/                          — Config Entities (WorkflowGroup, Workflow)
│   ├── Form/                            — Entity forms + ConditionsTableBuilder
│   ├── Hook/                            — Service classes for procedural hooks
│   ├── ListBuilder/                     — Admin listing pages
│   ├── Plugin/Field/FieldWidget/        — StateFieldRulesWidget
│   ├── Service/                         — Business logic services
│   ├── Validation/                      — Server-side conditional validation
│   └── ValueObject/                     — FieldType enum
├── js/mermaid-render.js                 — Mermaid rendering behavior
├── config/schema/                       — Config schema
└── state_machine_ui.install             — YAML migration on install
```

### Key services

| Service | Responsibility |
|---------|---------------|
| `WorkflowMetadataReader` | Reads custom metadata from workflow plugin definitions |
| `MetadataFilter` | Applies AND/OR filtering logic on states/transitions |
| `ConditionalFieldResolver` | Converts widget rules into Drupal `#states` arrays |
| `ConditionalRequiredValidator` | Server-side validation for conditionally required fields |
| `MermaidDiagramBuilder` | Generates Mermaid stateDiagram-v2 markup |
| `MermaidLibraryLocator` | Detects if Mermaid.js is installed locally |

## Metadata filtering — how it works

Declare custom metadata on states and transitions in your workflow YAML:

```yaml
states:
  draft:
    label: Draft
    tag:
      - can_be_edited
    category:
      - article
  published:
    label: Published
    tag:
      - need_unpublish
    category:
      - article

transitions:
  publish:
    label: Publish
    from: [draft]
    to: published
    requires_role:
      - editor
```

The module detects `tag`, `category`, `requires_role` automatically (reserved keys `label`, `from`, `to` are excluded). The admin sees checkboxes in the widget settings to filter which states/transitions are shown.

## Permissions

| Permission | Description |
|-----------|-------------|
| `administer state machine workflows` | Create, edit, delete workflow groups and workflows |
| `view state machine diagram` | See the Mermaid diagram on entity forms |

## Compatibility

- No modifications to the `state_machine` module
- No `#[Hook]` attributes (compatible with Drupal 10.3)
- No `\Drupal::` calls in `src/` — pure dependency injection
- Autowire enabled for all services
