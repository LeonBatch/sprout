/* global $, Craft, Garnish */

if (typeof Craft.SproutMeta === typeof undefined) {
    Craft.SproutMeta = {};
}

/**
 * Editable table class
 */
Craft.SproutMeta.EditableTable = Garnish.Base.extend(
    {
        initialized: false,

        id: null,
        baseName: null,
        columns: null,
        sorter: null,
        biggestId: -1,

        $table: null,
        $tbody: null,
        $addRowBtn: null,

        init: function(id, baseName, columns, settings) {
            this.id = id;
            this.baseName = baseName;
            this.columns = columns;
            this.setSettings(settings, Craft.SproutMeta.EditableTable.defaults);

            this.$table = $('#' + id);
            this.$tbody = this.$table.children('tbody');

            this.sorter = new Craft.DataTableSorter(this.$table, {
                helperClass: 'editabletablesorthelper',
                copyDraggeeInputValuesToHelper: true,
            });

            if (this.isVisible()) {
                this.initialize();
            } else {
                this.addListener(Garnish.$win, 'resize', 'initializeIfVisible');
            }
        },

        isVisible: function() {
            return (this.$table.height() > 0);
        },

        initialize: function() {
            if (this.initialized) {
                return;
            }

            this.initialized = true;
            this.removeListener(Garnish.$win, 'resize');

            const $rows = this.$tbody.children();

            for (let i = 0; i < $rows.length; i++) {
                new Craft.SproutMeta.EditableTable.Row(this, $rows[i]);
            }

            this.$addRowBtn = this.$table.next('.buttons').children('.add');

            this.addListener(this.$addRowBtn, 'activate', 'addRow');
        },

        initializeIfVisible: function() {
            if (this.isVisible()) {
                this.initialize();
            }
        },

        addRow: function() {
            const rowId = this.settings.rowIdPrefix + (this.biggestId + 1),
                rowHtml = Craft.SproutMeta.EditableTable.getRowHtml(rowId, this.columns, this.baseName, {}),
                $tr = $(rowHtml).appendTo(this.$tbody);

            new Craft.SproutMeta.EditableTable.Row(this, $tr);

            this.sorter.addItems($tr);

            // Focus the first input in the row
            $tr.find('input,textarea,select').first().focus();

            this.settings.onAddRow($tr);
        },
    },
    {
        textualColTypes: ['singleline', 'multiline', 'number'],
        defaults: {
            rowIdPrefix: '',
            onAddRow: $.noop,
            onDeleteRow: $.noop,
        },

        getRowHtml: function(rowId, columns, baseName, values) {

            let rowHtml = '<tr data-id="' + rowId + '">';
            for (let colId in columns) {
                const col = columns[colId],
                    name = baseName + '[' + rowId + '][' + colId + ']',
                    value = (typeof values[colId] !== 'undefined' ? values[colId] : ''),
                    textual = Craft.inArray(col.type, Craft.SproutMeta.EditableTable.textualColTypes);

                rowHtml += '<td class="' + (textual ? 'textual' : '') + ' ' + (typeof col['class'] !== 'undefined' ? col['class'] : '') + '"' +
                    (typeof col['width'] !== 'undefined' ? ' width="' + col['width'] + '"' : '') +
                    '>';

                switch (col.type) {
                case 'selectOther': {
                    const isOwnership = baseName.indexOf('ownership') > -1;
                    if (isOwnership) {
                        rowHtml += '<div class="field sprout-selectother"><div class="select sprout-selectotherdropdown"><select onchange="setDefault(this)" name="' + name + '">';
                    } else {
                        rowHtml += '<div class="field sprout-selectother"><div class="select sprout-selectotherdropdown"><select name="' + name + '">';
                    }

                    let hasOptgroups = false;

                    let firstRow = 'disabled selected';

                    for (let key in col.options) {
                        const option = col.options[key];

                        if (typeof option.optgroup !== 'undefined') {
                            if (hasOptgroups) {
                                rowHtml += '</optgroup>';
                            } else {
                                hasOptgroups = true;
                            }

                            rowHtml += '<optgroup label="' + option.optgroup + '">';
                        } else {
                            const optionLabel = (typeof option.label !== 'undefined' ? option.label : option),
                                optionValue = (typeof option.value !== 'undefined' ? option.value : key),
                                optionDisabled = (typeof option.disabled !== 'undefined' ? option.disabled : false);

                            rowHtml += '<option ' + firstRow + ' value="' + optionValue + '"' + (optionValue === value ? ' selected' : '') + (optionDisabled ? ' disabled' : '') + '>' + optionLabel + '</option>';
                        }

                        firstRow = '';
                    }

                    if (hasOptgroups) {
                        rowHtml += '</optgroup>';
                    }

                    rowHtml += '</select></div>';

                    rowHtml += '<div class="texticon clearable sprout-selectothertext hidden"><input class="text fullwidth" type="text" name="' + name + '" value="" autocomplete="off"><div class="clear" title="Clear"></div></div>';

                    rowHtml += '</div>';

                    break;
                }

                case 'checkbox': {
                    rowHtml += '<input type="hidden" name="' + name + '">' +
                            '<input type="checkbox" name="' + name + '" value="1"' + (value ? ' checked' : '') + '>';

                    break;
                }

                default: {
                    rowHtml += '<input class="text fullwidth" type="text" name="' + name + '" value="' + value + '">';
                }
                }

                rowHtml += '</td>';
            }

            rowHtml += '<td class="thin action"><a class="move icon" title="' + Craft.t('sprout', 'Reorder') + '"></a></td>' +
                '<td class="thin action"><a class="delete icon" title="' + Craft.t('sprout', 'Delete') + '"></a></td>' +
                '</tr>';

            return rowHtml;
        },

    });

/**
 * Editable table row class
 */
Craft.SproutMeta.EditableTable.Row = Garnish.Base.extend(
    {
        table: null,
        id: null,
        niceTexts: null,

        $tr: null,
        $tds: null,
        $textareas: null,
        $deleteBtn: null,

        init: function(table, tr) {
            this.table = table;
            this.$tr = $(tr);
            this.$tds = this.$tr.children();

            // Get the row ID, sans prefix
            const id = parseInt(this.$tr.attr('data-id').substr(this.table.settings.rowIdPrefix.length));

            if (id > this.table.biggestId) {
                this.table.biggestId = id;
            }

            this.$textareas = $();
            this.niceTexts = [];
            const textareasByColId = {};

            let i = 0;

            for (let colId in this.table.columns) {
                let col = this.table.columns[colId];

                if (Craft.inArray(col.type, Craft.SproutMeta.EditableTable.textualColTypes)) {
                    const $textarea = $('textarea', this.$tds[i]);
                    this.$textareas = this.$textareas.add($textarea);

                    this.addListener($textarea, 'focus', 'onTextareaFocus');
                    this.addListener($textarea, 'mousedown', 'ignoreNextTextareaFocus');

                    this.niceTexts.push(new Garnish.NiceText($textarea, {
                        onHeightChange: $.proxy(this, 'onTextareaHeightChange'),
                    }));

                    if (col.type === 'singleline' || col.type === 'number') {
                        this.addListener($textarea, 'keypress', {type: col.type}, 'validateKeypress');
                        this.addListener($textarea, 'textchange', {type: col.type}, 'validateValue');
                    }

                    textareasByColId[colId] = $textarea;
                }

                i++;
            }

            this.initSproutFields();

            // Now that all of the text cells have been nice-ified, let's normalize the heights
            this.onTextareaHeightChange();

            // Now look for any autopopulate columns
            for (let colId in this.table.columns) {
                /**
                 * @param {boolean} col.autopopulate
                 */
                let col = this.table.columns[colId];
                if (col.autopopulate && typeof textareasByColId[col.autopopulate] !== 'undefined' && !textareasByColId[colId].val()) {
                    new Craft.HandleGenerator(textareasByColId[colId], textareasByColId[col.autopopulate]);
                }
            }

            const $deleteBtn = this.$tr.children().last().find('.delete');
            this.addListener($deleteBtn, 'click', 'deleteRow');
        },

        initSproutFields: function() {
            Craft.SproutFields.initFields(this.$tr);
        },

        onTextareaFocus: function(ev) {
            this.onTextareaHeightChange();

            const $textarea = $(ev.currentTarget);

            if ($textarea.data('ignoreNextFocus')) {
                $textarea.data('ignoreNextFocus', false);
                return;
            }

            setTimeout(function() {
                const val = $textarea.val();

                // Does the browser support setSelectionRange()?
                if (typeof $textarea[0].setSelectionRange !== 'undefined') {
                    // Select the whole value
                    const length = val.length * 2;
                    $textarea[0].setSelectionRange(0, length);
                } else {
                    // Refresh the value to get the cursor positioned at the end
                    $textarea.val(val);
                }
            }, 0);
        },

        ignoreNextTextareaFocus: function(ev) {
            $.data(ev.currentTarget, 'ignoreNextFocus', true);
        },

        validateKeypress: function(ev) {
            const keyCode = ev.keyCode ? ev.keyCode : ev.charCode;

            if (!Garnish.isCtrlKeyPressed(ev) && (
                (keyCode === Garnish.RETURN_KEY) ||
                (ev.data.type === 'number' && !Craft.inArray(keyCode, Craft.SproutMeta.EditableTable.Row.numericKeyCodes))
            )) {
                ev.preventDefault();
            }
        },

        validateValue: function(ev) {
            let safeValue;

            if (ev.data.type === 'number') {
                // Only grab the number at the beginning of the value (if any)
                const match = ev.currentTarget.value.match(/^\s*(-?[\d.]*)/);

                if (match !== null) {
                    safeValue = match[1];
                } else {
                    safeValue = '';
                }
            } else {
                // Just strip any newlines
                safeValue = ev.currentTarget.value.replace(/[\r\n]/g, '');
            }

            if (safeValue !== ev.currentTarget.value) {
                ev.currentTarget.value = safeValue;
            }
        },

        onTextareaHeightChange: function() {
            // Keep all the textareas' heights in sync
            let tallestTextareaHeight = -1;

            for (let i = 0; i < this.niceTexts.length; i++) {
                if (this.niceTexts[i].height > tallestTextareaHeight) {
                    tallestTextareaHeight = this.niceTexts[i].height;
                }
            }

            this.$textareas.css('min-height', tallestTextareaHeight);

            // If the <td> is still taller, go with that insted
            const tdHeight = this.$textareas.first().parent().height();

            if (tdHeight > tallestTextareaHeight) {
                this.$textareas.css('min-height', tdHeight);
            }
        },

        deleteRow: function() {
            this.table.sorter.removeItems(this.$tr);
            this.$tr.remove();

            // onDeleteRow callback
            this.table.settings.onDeleteRow(this.$tr);
        },
    },
    {
        numericKeyCodes: [9 /* (tab) */, 8 /* (delete) */, 37, 38, 39, 40 /* (arrows) */, 45, 91 /* (minus) */, 46, 190 /* period */, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57 /* (0-9) */],
    });
