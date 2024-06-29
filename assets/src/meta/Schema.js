/* global $ */

class SproutMetaWebsiteIdentitySettings {

    constructor(props) {
        this.items = props.items;
        this.mainEntityValues = props.mainEntityValues;

        this.initLegacyCode();
        this.initOtherLegacyCode();
    }

    initLegacyCode() {
        let self = this;

        // Default option
        let option = '';

        // Method to clear dropdowns down to a given level
        let clearDropDown = function(arrayObj, startIndex) {
            $.each(arrayObj, function(index, value) {
                if (index >= startIndex) {
                    $(value).html(option);
                }
            });
        };

        // Method to disable dropdowns down to a given level
        let disableDropDown = function(arrayObj, startIndex) {
            $.each(arrayObj, function(index, value) {
                if (index >= startIndex) {
                    $(value).closest('div.organizationinfo-dropdown').addClass('hidden');
                }
            });
        };

        // Method to enable dropdowns down to a given level
        let enableDropDown = function(element) {
            element.closest('div.organizationinfo-dropdown').removeClass('hidden');
        };

        // Method to generate and append options
        let generateOptions = function(element, children) {
            let options = '';
            let name = '';

            $.each(children, function(index, value) {
                // insert space before capital letters
                name = index.replace(/([A-Z][^A-Z\b])/g, ' $1').trim();
                options += '<option value="' + index + '">' + name + '</option>';

                // let's foreach the children
                if (value) {
                    $.each(value, function(key) {
                        name = '&nbsp;&nbsp;&nbsp;' + key.replace(/([A-Z][^A-Z\b])/g, ' $1').trim();
                        options += '<option value="' + key + '">' + name + '</option>';
                    });
                }

            });

            element.append(options);
        };

        // Select each of the dropdowns
        let firstDropDown = $('.mainentity-firstdropdown select');
        let secondDropDown = $('.mainentity-seconddropdown select');

        // Hold selected option
        let firstSelection = '';

        // Selection handler for first level dropdown
        firstDropDown.on('change', function() {

            // Get selected option
            firstSelection = firstDropDown.val();

            let $organizationInfoInput = $('.organization-info :input');

            // Clear all dropdowns down to the hierarchy
            clearDropDown($organizationInfoInput, 1);

            // Disable all dropdowns down to the hierarchy
            disableDropDown($organizationInfoInput, 1);

            // Check current selection
            if (typeof self.items[firstSelection] === 'undefined' || firstSelection === '' || self.items[firstSelection].length <= 0) {
                return;
            }

            if (self.items[firstSelection][0]) {
                // Enable second level DropDown
                enableDropDown(secondDropDown);

                // Generate and append options
                generateOptions(secondDropDown, self.items[firstSelection][0]);
            }

        });

        // Selection handler for second level dropdown
        secondDropDown.on('change', function() {
            secondDropDown.val();
            // Final work goes here
        });
    }

    initOtherLegacyCode() {
        let self = this;
        let mainEntityValues = self.mainEntityValues;

        //Main entity dropdowns
        let $firstDropdownSelectInput = $('.mainentity-firstdropdown select');
        let $secondDropdownSelectInput = $('.mainentity-seconddropdown select');

        $firstDropdownSelectInput.change(function() {
            if (this.value === 'barrelstrength-sprout-schema-personschema') {
                $secondDropdownSelectInput.addClass('hidden');
            } else {
                $secondDropdownSelectInput.removeClass('hidden');
            }
        });

        // check if we need load depending dropdowns
        if (mainEntityValues) {
            if (Object.prototype.hasOwnProperty.call(mainEntityValues, 'schemaTypeId') && mainEntityValues.schemaTypeId) {
                $firstDropdownSelectInput.val(mainEntityValues.schemaTypeId).change();
            }
            if (Object.prototype.hasOwnProperty.call(mainEntityValues, 'schemaOverrideTypeId') && mainEntityValues.schemaOverrideTypeId) {
                $secondDropdownSelectInput.val(mainEntityValues.schemaOverrideTypeId).change();
            }
        }

    }
}

window.SproutMetaWebsiteIdentitySettings = SproutMetaWebsiteIdentitySettings;
