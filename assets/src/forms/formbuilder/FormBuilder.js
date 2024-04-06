export const FormBuilder = (formId) => ({

    formId: formId,

    lastUpdatedFormFieldUid: null,

    /**
     * Array of field data
     * [
     *  id: 123,
     *  type: className,
     *  etc.
     * ]
     */
    sourceFields: [],

    /**
     * [
     *   {
     *     id: 123,
     *     label: 'X',
     *     elements: [
     *        {
     *          id: 123,
     *          name: 'Y',
     *          required,
     *          getExampleHtml(),
     *          getSettingsHtml(),
     *        },
     *        {},
     *        {}
     *      ]
     *   }
     * ]
     */
    tabs: [],
    fields: [],
    uiSettings: [],

    fieldLayoutUid: null,

    selectedTabUid: null,
    editTabUid: null,
    editFieldUid: null,

    dragOrigin: null,

    DragOrigins: {
        sourceField: 'source-field',
        layoutField: 'layout-field',
        layoutTabNav: 'layout-tab-nav',
    },

    isDraggingTabUid: null,
    isDragOverTabUid: null,

    isDraggingFormFieldUid: null,
    isDragOverFormFieldUid: null,

    init() {

        let self = this;
        // Get the saved fieldLayout data
        Craft.sendActionRequest('POST', 'sprout-module-forms/form-builder/get-submission-field-layout', {
            data: {
                formId: this.formId,
            },
        }).then((response) => {
            console.log('get-submission-field-layout', response);
            // self.tabs = [
            //   {
            //     id: 123,
            //     label: 'Tab 1',
            //     fields: [],
            //   },
            // ];
            self.tabs = response.data.layout.tabs;
            // self.fields = response.data.layout.fields;
            // self.uiSettings = response.data.layout.uiSettings;
            self.fieldLayoutUid = response.data.layout.uid;

            // get uid of first tab in tabs array
            self.selectedTabUid = self.tabs[0].uid ?? null;

        }).catch(() => {
            console.log('No form data found.');
        });

        window.formBuilder = this;

        let sourceFields = JSON.parse(this.$refs.formBuilder.dataset.sourceFields);

        for (const field of sourceFields) {
            this.sourceFields.push(field);
        }
    },

    get integrationsFormFieldMetadata() {

        let fieldLayout = {};

        if (this.tabs.length && !this.tabs[0].elements.length) {
            return [];
        }

        let fields = [];

        for (const tab of this.tabs) {

            for (const element of tab.elements) {

                let fieldData = this.getFormFieldAttributes(element);

                let field = {
                    name: fieldData.field.name,
                    handle: fieldData.field.handle,
                    type: fieldData.field.type,
                    uid: fieldData.field.uid,
                };

                fields.push(field);
            }
        }

        this.fields = fields;

        return JSON.stringify(fields);
    },

    get fieldLayoutInputValue() {

        let fieldLayout = {};

        if (this.tabs.length && !this.tabs[0].elements.length) {
            return [];
        }

        let fieldLayoutTabs = [];

        for (const tab of this.tabs) {

            let fieldLayoutFields = [];

            for (const element of tab.elements) {

                let field = this.getFormFieldAttributes(element);

                fieldLayoutFields.push(field);
            }

            fieldLayoutTabs.push({
                id: tab.uid, // remove
                uid: tab.uid,
                name: tab.name,
                sortOrder: null,
                userCondition: null,
                elementCondition: null,
                elements: fieldLayoutFields,
            });
        }

        fieldLayout['tabs'] = fieldLayoutTabs;

        return JSON.stringify(fieldLayout);
    },

    // Removes uiSettings from element/field data
    getFormFieldAttributes(fieldData) {

        const {
            uiSettings,
            ...fieldAttributes
        } = fieldData;

        return fieldAttributes;
    },

    // Drag Actions

    dragStartLayoutTabNav(e) {
        console.log('dragStartLayoutTabNav');

        e.dataTransfer.setData('sprout/origin-page-tab-uid', e.target.dataset.tabUid);
        this.dragOrigin = this.DragOrigins.layoutTabNav;
        this.isDraggingTabUid = e.target.dataset.tabUid;

        // e.dataTransfer.dropEffect = 'link';
        // e.dataTransfer.effectAllowed = 'copyLink';
    },

    dragEndLayoutTabNav(e) {
        console.log('dragEndLayoutTabNav');

        this.dragOrigin = null;
        this.isDraggingTabUid = null;
        this.isDragOverTabUid = null;
    },

    dragEnterLayoutTabNav(e) {
        console.log('dragEnterLayoutTabNav');
        e.target.classList.add('no-pointer-events');
    },

    dragLeaveLayoutTabNav(e) {
        console.log('dragLeaveLayoutTabNav');
        e.target.classList.remove('no-pointer-events');
    },

    dragOverLayoutTabNav(e) {
        let self = this;

        if (this.dragOrigin === this.DragOrigins.layoutTabNav) {

        }

        if (this.dragOrigin === this.DragOrigins.sourceField || this.dragOrigin === this.DragOrigins.layoutField) {
            setTimeout(function() {
                self.selectedTabUid = e.target.dataset.tabUid;
            }, 1000);
        }
    },

    dropOnLayoutTabNav(e) {
        console.log('dropOnLayoutTabNav');

        let self = this;

        e.target.classList.remove('no-pointer-events');

        let originTabUid = e.dataTransfer.getData('sprout/origin-page-tab-uid');
        let targetTabUid = e.target.dataset.tabUid;

        if (this.dragOrigin === this.DragOrigins.layoutTabNav) {
            this.updateTabPosition(originTabUid, targetTabUid);
            this.selectedTabUid = originTabUid;
        }

        if (this.dragOrigin === this.DragOrigins.sourceField) {
            let type = e.dataTransfer.getData('sprout/field-type');
            this.addFieldToLayoutTab(type);
        }

        if (this.dragOrigin === this.DragOrigins.layoutField) {
            this.updateFieldPosition(originTabUid, targetTabUid, self.isDraggingFormFieldUid);
        }
    },

    dragStartSourceField(e) {
        console.log('dragStartSourceField');

        this.dragOrigin = this.DragOrigins.sourceField;

        e.dataTransfer.setData('sprout/field-type', e.target.dataset.type);

        // e.dataTransfer.dropEffect = 'link';
        // e.dataTransfer.effectAllowed = 'copyLink';
    },

    dragEndSourceField(e) {
        console.log('dragEndSourceField');

        this.isDraggingFormFieldUid = null;
        this.isDragOverFormFieldUid = null;
    },

    dragStartLayoutField(e) {
        console.log('dragStartLayoutField');

        let self = this;

        // Store selected tab in drag object as it might change before the drop event
        e.dataTransfer.setData('sprout/origin-page-tab-uid', this.selectedTabUid);
        e.dataTransfer.setData('sprout/field-type', e.target.dataset.type);
        this.dragOrigin = this.DragOrigins.layoutField;
        this.isDraggingTabUid = e.target.dataset.tabUid;
        this.isDraggingFormFieldUid = e.target.dataset.fieldUid;

        // Need setTimeout before manipulating dom:
        // https://stackoverflow.com/questions/19639969/html5-dragend-event-firing-immediately
        // setTimeout(function() {
        //   self.isDraggingFormFieldUid = isDraggingFormFieldUid;
        // }, 10);

        // e.dataTransfer.dropEffect = 'move';
        // e.dataTransfer.effectAllowed = 'move';

        // Handle scroll stuff: https://stackoverflow.com/a/72807140/1586560
        // On drag scroll, prevents page from growing with mobile safari rubber-band effect
        // let VerticalMaxed = (window.innerHeight + window.scrollY) >= document.body.offsetHeight;
        //
        // this.scrollActive = true;
        //
        // if (e.clientY < 150) {
        //   this.scrollActive = false;
        //   this.scrollFieldLayout(-1);
        // }
        //
        // if ((e.clientY > (document.documentElement.clientHeight - 150)) && !VerticalMaxed) {
        //   this.scrollActive = false;
        //   this.scrollFieldLayout(1)
        // }
    },

    dragEndLayoutField(e) {
        console.log('dragEndLayoutField');

        // Reset scrolling
        // this.scrollActive = false;

        this.isDraggingFormFieldUid = null;
        this.isDragOverFormFieldUid = null;
    },

    dragEnterLayoutTabBody(e) {
        console.log('dragEnterLayoutTabBody');

        this.isDragOverTabUid = this.selectedTabUid;
    },

    dragLeaveLayoutTabBody(e) {
        console.log('dragLeaveLayoutTabBody');

        this.isDragOverTabUid = null;
    },

    dropOnLayoutTabBody(e) {
        console.log('dropOnLayoutTabBody');
        let self = this;

        e.target.classList.remove('no-pointer-events');

        let type = e.dataTransfer.getData('sprout/field-type');
        let originTabUid = e.dataTransfer.getData('sprout/origin-page-tab-uid');

        if (this.dragOrigin === this.DragOrigins.sourceField) {
            this.addFieldToLayoutTab(type);

            let fieldData = this.getFieldByType(type);
            let layoutElement = this.getLayoutElement(fieldData.field.uid, fieldData.field, fieldData.uiSettings);
            this.editFormField(layoutElement);
        }

        if (this.dragOrigin === this.DragOrigins.layoutField) {
            let dropBeforeTargetFieldUid = e.target.dataset.fieldUid;
            this.updateFieldPosition(originTabUid, this.selectedTabUid, self.isDraggingFormFieldUid, dropBeforeTargetFieldUid);
        }

        console.log('tabs', this.tabs);
    },

    dragEnterLayoutField(e) {
        console.log('dragEnterLayoutField');
        e.target.classList.add('no-pointer-events');

    },

    dragLeaveLayoutField(e) {
        console.log('dragLeaveLayoutField');
        e.target.classList.remove('no-pointer-events');
    },

    dropOnExistingLayoutField(e) {
        console.log('dropOnExistingLayoutField');
        let self = this;

        e.target.classList.remove('no-pointer-events');

        // let fieldUid = e.dataTransfer.getData('sprout/field-id');
        let type = e.dataTransfer.getData('sprout/field-type');
        let originTabUid = e.dataTransfer.getData('sprout/origin-page-tab-uid');
        let targetTabUid = e.target.dataset.tabUid;
        let beforeFieldUid = e.target.dataset.fieldUid;

        if (this.dragOrigin === this.DragOrigins.sourceField) {
            this.addFieldToLayoutTab(type, beforeFieldUid);
        }

        if (this.dragOrigin === this.DragOrigins.layoutField) {
            this.updateFieldPosition(originTabUid, targetTabUid, self.isDraggingFormFieldUid, beforeFieldUid);
        }
    },

    dragEnterFieldLayoutEndZone(e) {
        console.log('dragEnterFieldLayoutEndZone');

        // this.isMouseOverEndZone = true;
    },

    dragLeaveFieldLayoutEndZone(e) {
        console.log('dragLeaveFieldLayoutEndZone');

        // this.isMouseOverEndZone = false;
    },

    dropOnLayoutEndZone(e) {
        console.log('dropOnLayoutEndZone');
        let self = this;

        e.target.classList.remove('no-pointer-events');

        let type = e.dataTransfer.getData('sprout/field-type');
        let originTabUid = e.dataTransfer.getData('sprout/origin-page-tab-uid');
        let targetTabUid = e.target.dataset.tabUid;
        let beforeFieldUid = e.target.dataset.fieldUid;

        if (this.dragOrigin === this.DragOrigins.sourceField) {
            this.addFieldToLayoutTab(type);
        }

        if (this.dragOrigin === this.DragOrigins.layoutField) {
            this.updateFieldPosition(originTabUid, targetTabUid, self.isDraggingFormFieldUid, beforeFieldUid);
        }
    },

    // See specifying drop targets docs:
    // https://developer.mozilla.org/en-US/docs/Web/API/HTML_Drag_and_Drop_API/Drag_operations#specifying_drop_targets
    dragOverLayoutTabBody(e) {
        const isDraggingFormField = e.dataTransfer.types.includes('sprout/field-type');

        if (isDraggingFormField) {
            console.log('dragOverLayoutTabBody');
            event.preventDefault();
        }
    },

    dragOverLayoutField(e) {
        const isDraggingLayoutField = e.dataTransfer.types.includes('sprout/field-type');

        if (isDraggingLayoutField) {
            // console.log('dragOverLayoutField');
            event.preventDefault();
        }
    },

    isOverFieldLayoutEndZone(e) {
        const sproutFormField = e.dataTransfer.types.includes('sprout/field-type');

        // this.isDragOverTabUid = this.selectedTabUid;
        // this.isDragOverFormFieldUid = e.target.parentNode.dataset.fieldUid;


        if (sproutFormField) {
            console.log('isOverFieldLayoutEndZone');
            event.preventDefault();
        }
    },

    // Helper Methods

    getFieldsByGroup(handle) {
        return this.sourceFields.filter(item => item.groupName === handle);
    },

    getFieldByType(type) {
        return this.sourceFields.filter(item => item.field.type === type)[0] ?? null;
    },

    getTabIndexByTabUid(tabUid) {
        return this.tabs.findIndex(item => item.uid === tabUid);
    },

    getFieldIndexByFieldUid(tab, fieldUid) {
        return tab.elements.findIndex(item => item.fieldUid === fieldUid);
    },

    updateTabSettings(tabUid, data) {
        let tabIndex = this.getTabIndexByTabUid(tabUid);

        // loop through js object
        Object.entries(data).forEach(([index, value]) => {
            this.tabs[tabIndex][index] = value;
        });

        if (!this.tabs[tabIndex]['name']) {
            this.tabs[tabIndex]['name'] = 'Page';
        }
    },

    updateFieldLayoutElement(fieldUid, fieldLayoutElement) {

        let tabIndex = this.getTabIndexByTabUid(this.selectedTabUid);
        let tab = this.tabs[tabIndex];
        let fieldIndex = this.getFieldIndexByFieldUid(tab, fieldUid);
        let targetFieldLayoutElement = tab.elements[fieldIndex];

        targetFieldLayoutElement.required = fieldLayoutElement.required;

        // Merge updated values into existing field
        targetFieldLayoutElement.field = Object.assign(targetFieldLayoutElement.field, fieldLayoutElement.field);

        tab.elements[fieldIndex] = targetFieldLayoutElement;
    },

    updateTabPosition(tabUid, beforeTabUid = null) {

        let beforeTabIndex = this.getTabIndexByTabUid(beforeTabUid);
        let tabIndex = this.getTabIndexByTabUid(tabUid);
        let targetTab = this.tabs[tabIndex];

        // console.log(this.tabs);
        // Remove the updated tab
        this.tabs.splice(tabIndex, 1);

        if (beforeTabUid) {

            // console.log('target' + targetTab);
            // Insert the updated tab before the target tab
            this.tabs.splice(beforeTabIndex, 0, targetTab);
        } else {
            this.tabs.push(targetTab);
        }

        this.lastUpdatedTabUid = targetTab.uid;
    },

    updateFieldPosition(originTabUid, targetTabUid, fieldUid, beforeFieldUid = null) {

        console.log('updateFieldPosition');

        let originTabIndex = this.getTabIndexByTabUid(originTabUid);
        let originTab = this.tabs[originTabIndex];

        let targetTabIndex = this.getTabIndexByTabUid(targetTabUid);
        let targetTab = this.tabs[targetTabIndex];

        if (!targetTab) {
            targetTab = originTab;
        }

        let originFieldIndex = this.getFieldIndexByFieldUid(originTab, fieldUid);
        let targetField = originTab.elements[originFieldIndex];

        // Remove the updated field from the layout
        // this might change the indexes of the elements on the tab
        originTab.elements.splice(originFieldIndex, 1);

        if (beforeFieldUid) {
            let beforeFieldIndex = this.getFieldIndexByFieldUid(targetTab, beforeFieldUid);

            // Insert the updated field before the target field
            targetTab.elements.splice(beforeFieldIndex, 0, targetField);
        } else {
            targetTab.elements.push(targetField);
        }

        // Update tab
        this.tabs[targetTabIndex] = targetTab;

        this.lastUpdatedFormFieldUid = targetField.uid;

        this.resetLastUpdated();
    },

    addFormPageButton() {
        let newUid = Craft.uuid();
        let tab = {
            uid: newUid,
            name: 'New Page',
            userCondition: null,
            elementCondition: null,
            elements: [],
        };

        this.tabs.push(tab);
        this.selectedTabUid = newUid;
    },

    addFieldToLayoutTab(type, beforeFieldUid = null) {

        console.log('addFieldToLayoutTab', type, beforeFieldUid);

        let fieldData = this.getFieldByType(type);
        fieldData.field.type = type;

        if (this.dragOrigin === this.DragOrigins.sourceField) {
            fieldData.field.name = fieldData.uiSettings.displayName;
            fieldData.field.handle = fieldData.uiSettings.defaultHandle + '_' + Craft.randomString(4);
            fieldData.field.uid = Craft.uuid();
        }

        if (this.dragOrigin === this.DragOrigins.layoutField) {

        }

        let fieldUid = fieldData.field.uid;

        let tabIndex = this.getTabIndexByTabUid(this.selectedTabUid);
        let layoutElement = this.getLayoutElement(fieldUid, fieldData.field, fieldData.uiSettings);
        this.tabs[tabIndex].elements.push(layoutElement);

        if (beforeFieldUid) {

            let tabIndex = this.getTabIndexByTabUid(this.selectedTabUid);
            let tab = this.tabs[tabIndex];

            let fieldIndex = this.getFieldIndexByFieldUid(tab, fieldUid);
            let targetField = tab.elements[fieldIndex];

            // Remove the updated field
            tab.elements.splice(fieldIndex, 1);

            // let beforeFieldIndex = tab.elements.length + 1;
            let beforeFieldIndex = this.getFieldIndexByFieldUid(tab, beforeFieldUid);

            // Insert the updated field before the target field
            tab.elements.splice(beforeFieldIndex, 0, targetField);

            // Update tab
            this.tabs[tabIndex] = tab;

            this.lastUpdatedFormFieldUid = targetField.uid;

            this.resetLastUpdated();
        }
    },

    getLayoutElement(fieldUid, field, uiSettings) {
        return {
            type: 'BarrelStrength\\Sprout\\forms\\submissions\\CustomFormField',
            required: false,
            width: 100,
            uid: Craft.uuid(),
            userCondition: null,
            elementCondition: null,
            fieldUid: fieldUid,
            field: field,
            uiSettings: uiSettings,
        };
    },

    resetLastUpdated() {
        let self = this;

        // The timeout here needs to match the time of the 'drop-highlight' css transition effect
        setTimeout(function() {
            self.lastUpdatedFormFieldUid = null;
        }, 300);
    },

    // scrollFieldLayout(stepY) {
    //   let scrollY = document.documentElement.scrollTop || document.body.scrollTop;
    //   window.scrollTo(0, (scrollY + stepY));
    //
    //   if (this.scrollActive) {
    //     setTimeout(function() {
    //       scroll(0, stepY);
    //     }, 20);
    //   }
    // },

    isBefore(element1, element2) {
        if (element2.parentNode === element1.parentNode) {
            for (let currentElement = element1.previousSibling; currentElement && currentElement.nodeType !== 9; currentElement = currentElement.previousSibling) {
                if (currentElement === element2) {
                    return true;
                }
            }
        }
        return false;
    },

    // Field Stuff

    editFormTab(tab) {

        let self = this;

        this.editTabUid = tab.uid;

        Craft.sendActionRequest('POST', 'sprout-module-forms/form-builder/get-form-tab-settings-html', {
            data: {
                formId: this.formId,
                tab: tab,
            },
        }).then((response) => {

            self.openTabSlideout(response);

        }).catch(() => {
            console.log('No form data found.');
        });

    },

    openTabSlideout(response) {

        let self = this;

        const $body = $('<div/>', {class: 'fld-element-settings-body'});
        const $fields = $('<div/>', {class: 'fields'}).appendTo($body);
        const $footer = $('<div/>', {class: 'fld-element-settings-footer'});

        const $removeBtn = Craft.ui.createButton({
            class: 'icon',
            attr: {
                'data-icon': 'trash',
            },
            label: Craft.t('app', 'Remove'),
            spinner: true,
        });

        $removeBtn.attr('data-icon', 'trash');

        // Copied from Craft's FieldLayoutDesigner.js
        const $cancelBtn = Craft.ui.createButton({
            data: {
                trashed: true,
            },
            label: Craft.t('app', 'Close'),
            spinner: true,
        });

        const $applyButton = Craft.ui.createSubmitButton({
            class: 'secondary',
            label: Craft.t('app', 'Apply'),
            spinner: true,
        });

        $removeBtn.appendTo($footer);
        $('<div/>', {class: 'flex-grow'}).appendTo($footer);
        $cancelBtn.appendTo($footer);
        $applyButton.appendTo($footer);

        let settingsHtml = self.swapPlaceholders(response.data.settingsHtml, response.data.tabUid);

        $(settingsHtml).appendTo($fields);

        const $contents = $body.add($footer);

        // Make sure condition builder js is only added once
        $('.sprout-conditionbuilder-slideout').remove();

        const slideout = new Craft.Slideout($contents, {
            containerElement: 'form',
            containerAttributes: {
                method: 'post',
                action: '',
                class: 'fld-element-settings slideout sprout-conditionbuilder-slideout',
                id: 'sprout-tab-modal',
            },
        });

        const $form = slideout.$container;

        let tabSettingsJs = self.swapPlaceholders(response.data.tabSettingsJs, response.data.tabUid);
        Craft.appendBodyHtml(tabSettingsJs);

        $form.on('submit', function(event) {
            event.preventDefault();

            let formData = new FormData($form[0]);

            Craft.sendActionRequest('POST', 'sprout-module-forms/form-builder/get-form-tab-object', {
                data: formData,
            }).then((response) => {
                self.updateTabSettings(self.editTabUid, {
                    name: response.data.name,
                    // userCondition: response.data.userCondition,
                    // elementCondition: response.data.elementCondition,
                });
            });

            slideout.close();
        });

        $removeBtn.on('click', () => {
            // if only 1 tab exists, don't allow it to be removed
            if (self.tabs.length === 1) {
                Craft.cp.displayNotice(Craft.t('sprout-module-forms', 'Form must contain at least one tab.'));

                return;
            }

            let tabIndex = self.getTabIndexByTabUid(self.selectedTabUid);

            let newSelectedTabUid = (tabIndex - 1) >= 0
                ? self.tabs[tabIndex - 1].uid
                : self.tabs[tabIndex + 1].uid;

            self.tabs.splice(tabIndex, 1);

            let newTabIndex = self.getTabIndexByTabUid(newSelectedTabUid);
            self.selectedTabUid = self.tabs[newTabIndex].uid;
            self.editTabUid = self.selectedTabUid;

            slideout.close();
        });

        $cancelBtn.on('click', () => {
            slideout.close();
            self.editFieldUid = null;
        });
    },

    editFormField(layoutElement) {
        let self = this;

        self.editFieldUid = layoutElement.fieldUid;

        let data = {
            formId: this.formId,
            layoutElement: layoutElement,
        };

        console.log('Edit Form Field Layout Element', layoutElement);

        let slideout = new Craft.CpScreenSlideout('sprout-module-forms/form-builder/edit-form-field-slideout-via-cp-screen', {
            hasTabs: true,
            tabManager: '',
            params: {
                formId: this.formId,
                layoutElement: layoutElement,
            },
        });

        const $removeBtn = Craft.ui.createButton({
            class: 'icon',
            label: Craft.t('app', 'Remove'),
            spinner: true,
        });

        $removeBtn.attr('data-icon', 'trash');

        // Place remove button before flex-grow div to keep it on the left
        slideout.$footer.find('.flex-grow').before($removeBtn);

        // remove slideout.$footer primary button class primary
        slideout.$footer.find('.submit').addClass('secondary');

        $removeBtn.on('click', () => {
           console.log('waa');
           slideout.close();
        });

        // let settingsHtml = self.swapPlaceholders(response.data.settingsHtml, response.data.fieldUid);
        slideout.on('submit', ev => {
            let layoutElement = ev.response.data.layoutElement ?? {};
           console.log('layoutElement', layoutElement);
           self.updateFieldLayoutElement(self.editFieldUid, layoutElement);
            console.log('tabs', self.tabs);
        });

        slideout.on('close', () => {
            console.log('Close Edit Form Field Slideout');
        });

        // init ui elements on slideout
        Craft.initUiElements(slideout);

    },

    // The js output by the condition builder
    // "<script>
    // const conditionBuilderJs = "<script>Garnish.requestAnimationFrame(() => {
    //   const $button = $('#sources-__SOURCE_KEY__-condition-type-btn');
    //   $button.menubtn().data('menubtn').on('optionSelect', event => {
    //     const $option = $(event.option);
    //     $button.text($option.text()).removeClass('add');
    // // Don\'t use data(\'value\') here because it could result in an object if data-value is JSON
    //     const $input = $('#sources-__SOURCE_KEY__-condition-type-input').val($option.attr('data-value'));
    //     htmx.trigger($input[0], 'change');
    //   });
    // });
    // htmx.process(htmx.find('#__ID__'));
    // htmx.trigger(htmx.find('#__ID__'), 'htmx:load');
    // </script>";
    swapPlaceholders(str, sourceKey) {
        const defaultId = `condition${Math.floor(Math.random() * 1000000)}`;

        return str
            .replace(/__ID__/g, defaultId)
            .replace(/__SOURCE_KEY__(?=-)/g, Craft.formatInputId('"' + sourceKey + '"'))
            .replace(/__SOURCE_KEY__/g, sourceKey);
    },
});
