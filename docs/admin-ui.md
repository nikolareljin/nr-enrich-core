# Admin UI Extension

NR EnrichCore registers a Pimcore ExtJS 6 plugin that adds an **Enrich with AI** button
to every DataObject editor toolbar.

## How it works

The JS file `nr-enrich-core.js` is declared in `NrEnrichCoreBundle::getJsPaths()`.
Pimcore loads it automatically in the admin backend after `assets:install`.

The plugin registers itself with `pimcore.plugin.broker` and hooks into the
`postOpenObject` event. When any DataObject is opened, the button is injected into
the toolbar.

## Toolbar button

The button is added only once per object instance (guarded by `itemId` check to
prevent double-injection on re-open). It uses the `pimcore_icon_magic_wand` CSS class
for the icon.

## Enrichment panel

Clicking the button opens an `Ext.window.Window` containing a form with:

| Field | Description |
|---|---|
| Class (display) | Auto-populated from the open object |
| Fields | Comma-separated field names, or empty for all configured fields |
| Provider | Named provider key, or empty for bundle default |
| Prompt template | Override template with `{{ value }}`, `{{ objectId }}`, `{{ class }}` |

## API call

On submit, the plugin POSTs to `/admin/nrec/enrich`. On success, it:
1. Shows a Pimcore success notification with the number of fields updated.
2. Calls `pimcore.helpers.reloadObject(objectId)` to refresh the editor with new values.

## Customisation

To extend the panel (e.g. add a language selector), override the JS file in your own
bundle by placing a file at:

```
public/bundles/nrenrichcore/js/nr-enrich-core.js
```

Pimcore's asset override mechanism will prefer your version.

## i18n

The button label uses `t('nrec_enrich_with_ai')` which resolves to `'Enrich with AI'`
if no translation is found. Add a Pimcore admin translation key `nrec_enrich_with_ai`
in your admin translations file to localise it.
