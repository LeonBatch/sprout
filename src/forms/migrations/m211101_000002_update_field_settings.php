<?php

/** @noinspection ClassConstantCanBeUsedInspection */

namespace BarrelStrength\Sprout\forms\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Json;

/**
 * @role temporary: Craft 4 => 5
 * @schema sprout-module-forms
 * @deprecated Remove in craftcms/cms:6.0
 */
class m211101_000002_update_field_settings extends Migration
{
    public function safeUp(): void
    {
        $this->removeRetiredFieldTypes();

        // @todo - Look at every field setting that exist and make sure
        // it's the data type we expect it to be
        // Should we make a helper to audit these?
        // https://github.com/barrelstrength/craft-sprout-forms/issues/490

        $this->cleanUpAddressFieldStuff();
        $this->cleanUpPredefinedFieldStuff();
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }

    public function removeRetiredFieldTypes(): void
    {
        // @todo = review
        $notesFieldIds = (new Query())
            ->select(['id'])
            ->from('{{%fields}}')
            ->where(['type' => 'barrelstrength\sproutfields\fields\Notes'])
            ->column();

        $notesFieldUids = (new Query())
            ->select(['uid'])
            ->from('{{%fields}}')
            ->where(['type' => 'barrelstrength\sproutfields\fields\Notes'])
            ->column();

        $fieldLayoutIds = [];
        //$fieldLayoutIds = (new Query())
        //    ->select(['layoutId'])
        //    ->from('{{%fieldlayoutfields}}')
        //    ->where(['in', 'fieldId', $notesFieldIds])
        //    ->distinct()
        //    ->column();

        // fieldlayouts table
        //{"tabs": [{"uid": "1038210f-44bb-4e63-9652-652b2c69f2ba", "name": "Content", "elements": [{"id": null, "max": null, "min": null, "tip": null, "uid": "d8ac7db2-e6d1-4a60-85dc-1cc9ef2a7bda", "name": null, "size": null, "step": null, "type": "craft\\fieldlayoutelements\\TitleField", "class": null, "label": null, "title": null, "width": 100, "warning": null, "disabled": false, "readonly": false, "inputType": null, "requirable": false, "autocorrect": true, "orientation": null, "placeholder": null, "autocomplete": false, "instructions": null, "userCondition": null, "autocapitalize": true, "includeInCards": false, "providesThumbs": false, "labelAttributes": [], "elementCondition": null, "containerAttributes": [], "inputContainerAttributes": []}], "userCondition": null, "elementCondition": null}]}


        $layouts = (new Query())
            ->select([
                'entryTypeUid' => 'entrytypes.uid',
                'fieldLayoutUid' => 'fieldlayouts.uid',
            ])
            ->from(['entrytypes' => '{{%entrytypes}}'])
            ->innerJoin(['fieldlayouts' => '{{%fieldlayouts}}'],
                '[[fieldlayouts.id]] = [[entrytypes.fieldLayoutId]]')
            ->where(['in', 'fieldlayouts.id', $fieldLayoutIds])
            ->all();

        $projectConfig = Craft::$app->getProjectConfig();

        $notesFieldsHaveAlreadyBeenUpdated = false;

        // Loop through all the layouts that have Notes fields
        foreach ($layouts as $layout) {
            if (!isset($layout['entryTypeUid'], $layout['fieldLayoutUid'])) {
                continue;
            }

            $tabsPath = 'entryTypes.' . $layout['entryTypeUid'] . '.fieldLayouts.' . $layout['fieldLayoutUid'] . '.tabs';

            $tabs = $projectConfig->get($tabsPath);
            $fields = $projectConfig->get('fields');

            $newTabs = [];

            // Loop through all the tabs of the layout
            foreach ($tabs as $tab) {

                // Loop through each field or UI element found in the layout
                foreach ($tab['fields'] as $key => $element) {

                    // Get UID of the Notes field we're looking for.
                    if (isset($element['fieldUid']) &&
                        in_array($element['fieldUid'], $notesFieldUids, true)) {
                        $field = $fields[$element['fieldUid']] ?? null;

                        if (!$field) {
                            continue;
                        }

                        $oldStyle = $field['settings']['style'] ?? null;
                        $notes = $field['settings']['notes'] ?? null;

                        if ($oldStyle === 'warning' || $oldStyle === 'tip') {
                            $notesFieldsHaveAlreadyBeenUpdated = true;
                        }

                        if ($oldStyle === 'warningDocumentation' || $oldStyle === 'dangerDocumentation') {
                            $newStyle = 'warning';
                            $newType = 'craft\fieldlayoutelements\Warning';
                        } else {
                            $newStyle = 'tip';
                            $newType = 'craft\fieldlayoutelements\Tip';
                        }

                        $tab['fields'][$key] = [
                            'style' => $newStyle,
                            'tip' => $notes ?? '',
                            'type' => $newType,
                        ];
                    }
                }

                $newTabs[] = $tab;

                if (!$notesFieldsHaveAlreadyBeenUpdated) {
                    // Then save the project config remove field with the UID in question.
                    $projectConfig->set($tabsPath, $newTabs);
                }
            }
        }

        // Log previous data for user to refer to if needed.
        $this->delete(Table::FIELDS, ['id' => $notesFieldIds]);
    }

    public function cleanUpAddressFieldStuff(): void
    {
        $fields = (new Query())
            ->select(['id', 'settings'])
            ->from('{{%fields}}')
            ->where(['type' => 'barrelstrength\sproutfields\fields\Address'])
            ->orWhere(['type' => 'barrelstrength\sproutforms\fields\formfields\Address'])
            ->all();

        // Remove deprecated attributes and resave settings
        foreach ($fields as $field) {
            $id = $field['id'];
            $settings = Json::decode($field['settings']);

            unset(
                $settings['addressHelper'],
                $settings['hideCountryDropdown'],
            );

            $this->update('{{%fields}}', [
                'settings' => Json::encode($settings),
            ], ['id' => $id], [], false);
        }
    }

    public function cleanUpPredefinedFieldStuff(): void
    {
        $fields = (new Query())
            ->select(['id', 'settings'])
            ->from('{{%fields}}')
            ->where(['type' => 'barrelstrength\sproutfields\fields\Predefined'])
            ->orWhere(['type' => 'barrelstrength\sproutfields\fields\PredefinedDate'])
            ->all();

        // Remove deprecated attributes and resave settings
        foreach ($fields as $field) {
            $id = $field['id'];
            $settings = Json::decode($field['settings']);

            unset(
                $settings['contentColumnType'],
                $settings['outputTextarea'],
            );

            $this->update('{{%fields}}', [
                'settings' => Json::encode($settings),
            ], ['id' => $id], [], false);
        }
    }
}
