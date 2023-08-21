<?php

namespace BarrelStrength\Sprout\forms\components\formthemes;

use BarrelStrength\Sprout\core\fieldlayoutelements\LightswitchField;
use BarrelStrength\Sprout\core\fieldlayoutelements\SelectField;
use BarrelStrength\Sprout\forms\components\elements\FormElement;
use BarrelStrength\Sprout\forms\formthemes\FormTheme;
use BarrelStrength\Sprout\uris\links\fieldlayoutelements\EnhancedLinkField;
use Craft;
use craft\fieldlayoutelements\HorizontalRule;
use craft\fieldlayoutelements\TextareaField;
use craft\fieldlayoutelements\TextField;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

class DefaultFormTheme extends FormTheme
{
    public ?string $formTemplate = '@Sprout/TemplateRoot/forms/default';

    public static function displayName(): string
    {
        return Craft::t('sprout-module-forms', 'Default Templates');
    }

    public function getFieldLayout(): FieldLayout
    {
        if ($this->_fieldLayout) {
            return $this->_fieldLayout;
        }

        $fieldLayout = new FieldLayout([
            'type' => FormElement::class,
        ]);

        $fieldLayoutTab = new FieldLayoutTab([
            'layout' => $fieldLayout,
            'name' => Craft::t('sprout-module-forms', 'Behavior'),
            'sortOrder' => 1,
            'uid' => StringHelper::UUID(),
        ]);

        $fieldLayoutTab->setElements([
            new TextField([
                'label' => Craft::t('sprout-module-forms', 'Submit Button'),
                'instructions' => Craft::t('sprout-module-forms', 'The text displayed for the submit button.'),
                'attribute' => 'submitButtonText',
                'uid' => 'SPROUT-UID-FORMS-SUBMIT-BUTTON-TEXT-FIELD',
            ]),
            new EnhancedLinkField([
                'label' => Craft::t('sprout-module-forms', 'Redirect Page'),
                'instructions' => Craft::t('sprout-module-forms', 'Where should the user be redirected upon form submission? Leave blank to redirect user back to the form.'),
                'attribute' => 'redirectUri',
            ]),
            new HorizontalRule([
                'uid' => 'SPROUT-UID-FORMS-HORIZONTAL-RULE-SUBJECT-CONTENT-1',
            ]),
            new SelectField([
                'label' => Craft::t('sprout-module-forms', 'Error Display Method'),
                'instructions' => Craft::t('sprout-module-forms', 'The method used to display errors after a form fails validation.'),
                'attribute' => 'errorDisplayMethod',
                'options' => [
                    [
                        'label' => 'Inline - Display errors alongside their respective fields',
                        'value' => 'inline',
                    ],
                    [
                        'label' => 'Globally - Display errors together in a list',
                        'value' => 'global',
                    ],
                    [
                        'label' => 'Inline & Globally - Display errors everywhere!',
                        'value' => 'both',
                    ]
                ],
            ]),
            new TextareaField([
                'label' => Craft::t('sprout-module-forms', 'Success Message'),
                'instructions' => Craft::t('sprout-module-forms', 'The message displayed after a submission is successfully submitted. Leave blank for no message.'),
                'placeholder' => Craft::t('sprout-module-forms', "Thanks! We'll be in touch."),
                'attribute' => 'messageOnSuccess',
                'class' => 'nicetext fullwidth',
                'rows' => 5,
                'mandatory' => true,
                'uid' => 'SPROUT-UID-FORMS-MESSAGE-ON-SUCCESS-FIELD',
            ]),
            new TextareaField([
                'label' => Craft::t('sprout-module-forms', 'Error Message'),
                'instructions' => Craft::t('sprout-module-forms', "The message displayed when a form submission has errors. This message will display above the error list if 'Globally' is selected as the Error Display Method. Leave blank for no message."),
                'placeholder' => Craft::t('sprout-module-forms', 'We were unable to process your submission. Please correct any errors and submit the form again.'),
                'attribute' => 'messageOnError',
                'class' => 'nicetext fullwidth',
                'rows' => 5,
                'mandatory' => true,
                'uid' => 'SPROUT-UID-FORMS-MESSAGE-ON-ERROR-FIELD',
            ]),
            new HorizontalRule([
                'uid' => 'SPROUT-UID-FORMS-HORIZONTAL-RULE-SUBJECT-CONTENT-2',
            ]),
            new SelectField([
                'label' => Craft::t('sprout-module-forms', 'Submission Method'),
                'instructions' => Craft::t('sprout-module-forms', 'The method used to submit your forms.'),
                'attribute' => 'submissionMethod',
                'options' => [
                    [
                        'label' => 'Reload Page (Synchronous)',
                        'value' => 'sync',
                    ],
                    [
                        'label' => 'AJAX (Asynchronous)',
                        'value' => 'async',
                    ],
                ],
            ]),
            new LightswitchField([
                'label' => Craft::t('sprout-module-forms', 'Page Titles'),
                'instructions' => Craft::t('sprout-module-forms', 'Display Page Titles on Forms.'),
                'attribute' => 'displaySectionTitles',
                'onLabel' => Craft::t('sprout-module-forms', 'Show'),
                'offLabel' => Craft::t('sprout-module-forms', 'Hide'),
                'uid' => 'SPROUT-UID-FORMS-PAGE-TITLES-FIELD',
            ]),
            new LightswitchField([
                'label' => Craft::t('sprout-module-forms', 'Enable Captchas'),
                'instructions' => Craft::t('sprout-module-forms', 'Enable or disable the global captchas for this specific form.'),
                'attribute' => 'enableCaptchas',
                'onLabel' => Craft::t('sprout-module-forms', 'Enable'),
                'offLabel' => Craft::t('sprout-module-forms', 'Disable'),
                'uid' => 'SPROUT-UID-FORMS-ENABLE-CAPTCHAS-FIELD',
            ]),
        ]);

        $fieldLayout->setTabs([
            $fieldLayoutTab,
        ]);

        return $this->_fieldLayout = $fieldLayout;
    }
}



