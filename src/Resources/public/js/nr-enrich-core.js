/**
 * NR EnrichCore — Pimcore Admin UI Extension
 *
 * Registers a Pimcore ExtJS plugin that:
 *  1. Adds an "Enrich with AI" button to the DataObject editor toolbar.
 *  2. Opens a configuration panel (provider selector + field checkboxes + prompt preview).
 *  3. POSTs to /admin/nrec/enrich and shows enrichment results inline.
 *
 * Requires Pimcore 11 (ExtJS 6 / Sencha Touch).
 */

/* global pimcore, Ext */

pimcore.registerNS('pimcore.plugin.nrEnrichCore');

pimcore.plugin.nrEnrichCore = Class.create(pimcore.plugin.admin, {

    getClassName: function () {
        return 'pimcore.plugin.nrEnrichCore';
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    // ── Lifecycle hooks ──────────────────────────────────────────────────────

    postOpenObject: function (object, type) {
        if (type !== 'object' && type !== 'variant') {
            return;
        }
        this._injectToolbarButton(object);
    },

    // ── Toolbar button ───────────────────────────────────────────────────────

    _injectToolbarButton: function (object) {
        var toolbar = object.toolbar;
        if (!toolbar) {
            return;
        }

        // Avoid double-injection on re-open.
        if (toolbar.down('#nrEnrichCoreBtn')) {
            return;
        }

        toolbar.add({
            xtype: 'button',
            itemId: 'nrEnrichCoreBtn',
            text: t('nrec_enrich_with_ai') || 'Enrich with AI',
            iconCls: 'pimcore_icon_magic_wand',
            tooltip: 'NR EnrichCore — AI Field Enrichment',
            handler: this._openEnrichPanel.bind(this, object),
        });
    },

    // ── Enrichment panel ─────────────────────────────────────────────────────

    _openEnrichPanel: function (object) {
        var className   = object.data.general.className;
        var objectId    = object.id;
        var objectTitle = object.data.general.key || ('#' + objectId);

        var win = Ext.create('Ext.window.Window', {
            title: 'Enrich with AI — ' + objectTitle,
            width: 560,
            minHeight: 320,
            layout: 'fit',
            modal: true,
            resizable: true,
            items: [this._buildPanelForm(objectId, className, win)],
        });

        win.show();
    },

    _buildPanelForm: function (objectId, className, win) {
        var me = this;

        return Ext.create('Ext.form.Panel', {
            bodyPadding: 16,
            defaults: { anchor: '100%' },
            items: [
                {
                    xtype: 'displayfield',
                    fieldLabel: 'Class',
                    value: className,
                },
                {
                    xtype: 'textfield',
                    name: 'fields',
                    fieldLabel: 'Fields',
                    emptyText: 'description, shortDescription (comma-separated, empty = all)',
                    allowBlank: true,
                },
                {
                    xtype: 'textfield',
                    name: 'provider',
                    fieldLabel: 'Provider',
                    emptyText: 'default (uses bundle default)',
                    allowBlank: true,
                },
                {
                    xtype: 'textarea',
                    name: 'promptTemplate',
                    fieldLabel: 'Prompt template',
                    emptyText: 'Improve this text: {{ value }}  (leave empty to use configured template)',
                    height: 80,
                    allowBlank: true,
                },
                {
                    xtype: 'displayfield',
                    value: '<small>Placeholders: <code>{{ value }}</code>, <code>{{ objectId }}</code>, <code>{{ class }}</code></small>',
                },
            ],
            buttons: [
                {
                    text: 'Cancel',
                    handler: function () { win.close(); },
                },
                {
                    text: 'Enrich',
                    iconCls: 'pimcore_icon_magic_wand',
                    formBind: false,
                    handler: function (btn) {
                        var form     = btn.up('form');
                        var values   = form.getValues();
                        var rawFields = (values.fields || '').trim();
                        var provider  = (values.provider || '').trim() || 'default';
                        var prompt    = (values.promptTemplate || '').trim();

                        var fieldList = rawFields.length > 0
                            ? rawFields.split(',').map(function (f) { return f.trim(); }).filter(Boolean)
                            : [];

                        var fieldDefs = fieldList.length > 0
                            ? fieldList.map(function (f) {
                                var def = { fieldName: f, provider: provider };
                                if (prompt) { def.promptTemplate = prompt; }
                                return def;
                            })
                            : [{ fieldName: '__all__', provider: provider }];

                        me._submitEnrichment(objectId, className, fieldDefs, win);
                    },
                },
            ],
        });
    },

    // ── API call ─────────────────────────────────────────────────────────────

    _submitEnrichment: function (objectId, className, fieldDefs, win) {
        win.setLoading('Enriching…');

        Ext.Ajax.request({
            url: '/admin/nrec/enrich',
            method: 'POST',
            jsonData: {
                objectId:  objectId,
                className: className,
                fields:    fieldDefs,
            },
            success: function (response) {
                win.setLoading(false);
                var data = Ext.decode(response.responseText);
                if (data && data.success) {
                    pimcore.helpers.showNotification(
                        'NR EnrichCore',
                        'Enrichment complete — ' + (data.results || []).length + ' field(s) updated.',
                        'success'
                    );
                    win.close();
                    // Reload the object editor to show new values.
                    pimcore.helpers.reloadObject(objectId);
                } else {
                    Ext.Msg.alert('Enrichment failed', (data && data.error) ? data.error : 'Unknown error');
                }
            },
            failure: function (response) {
                win.setLoading(false);
                var data = Ext.decode(response.responseText) || {};
                Ext.Msg.alert('Enrichment failed', data.error || 'Network error (' + response.status + ')');
            },
        });
    },
});

// Auto-instantiate.
var nrEnrichCorePlugin = new pimcore.plugin.nrEnrichCore();
