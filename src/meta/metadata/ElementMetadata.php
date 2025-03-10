<?php

namespace BarrelStrength\Sprout\meta\metadata;

use BarrelStrength\Sprout\meta\components\fields\ElementMetadataField;
use BarrelStrength\Sprout\meta\MetaModule;
use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\db\Query;
use craft\db\Table;
use craft\models\FieldLayout;
use yii\base\Component;

class ElementMetadata extends Component
{
    public ?array $_rawMetadata = null;

    /**
     * Returns the metadata for an Element's Element Metadata as a Metadata model
     */
    public function getRawMetadataFromElement(ElementInterface $element): array
    {
        if ($this->_rawMetadata !== null) {
            return $this->_rawMetadata;
        }

        $fieldHandle = $this->getElementMetadataFieldHandle($element);

        if ($fieldHandle && $metadata = $element->{$fieldHandle} ?? null) {
            $this->_rawMetadata = $metadata->getRawData();
        }

        return $this->_rawMetadata ?? [];
    }

    /**
     * Returns the Field handle of the first Element Metadata field found in an Element Field Layout
     */
    public function getElementMetadataFieldHandle(ElementInterface $element = null): ?string
    {
        if ($element === null) {
            return null;
        }

        /** @var FieldLayout $fieldLayout */
        $fieldLayout = $element->getFieldLayout();
        $fields = $fieldLayout->getCustomFields();

        /**  @var Field $field */
        foreach ($fields as $field) {
            if (($field::class == ElementMetadataField::class) && isset($element->{$field->handle})) {
                return $field->handle;
            }
        }

        return null;
    }

    public function getMetaBadgeInfo($settings): array
    {
        $targetSettings = [
            [
                'type' => 'optimizedTitleField',
                'targetFieldHandles' => $settings['optimizedTitleFieldFormat'] ?? $settings['optimizedTitleField'],
                'badgeClass' => 'sprout-metatitle-info',
            ], [
                'type' => 'optimizedDescriptionField',
                'targetFieldHandles' => $settings['optimizedDescriptionFieldFormat'] ?? $settings['optimizedDescriptionField'],
                'badgeClass' => 'sprout-metadescription-info',
            ], [
                'type' => 'optimizedImageField',
                'targetFieldHandles' => $settings['optimizedImageFieldFormat'] ?? $settings['optimizedImageField'],
                'badgeClass' => 'sprout-metaimage-info',
            ],
        ];

        $metaFieldHandles = [];

        foreach ($targetSettings as $targetSetting) {
            $handles = $this->getFieldHandles($targetSetting['targetFieldHandles']);

            foreach ($handles as $handle) {
                if (isset($metaFieldHandles[$handle])) {
                    continue;
                }

                $metaFieldHandles[$handle] = [
                    'type' => $targetSetting['type'],
                    'handle' => $handle,
                    'badgeClass' => $targetSetting['badgeClass'],
                ];
            }
        }
        
        return $metaFieldHandles;
    }

    public function getFieldHandles(string $targetFieldSetting = null): array
    {
        $targetField = $targetFieldSetting ?? null;

        if (!$targetField) {
            return [];
        }

        // Return the handle of the selected field and make into an array
        $existingFieldHandle = [$this->getExistingFieldHandle($targetField)];

        // Parse a custom setting and return an array or empty array
        $customSettingFieldHandles = $this->getCustomSettingFieldHandles($targetField);

        $fieldHandles = array_filter(array_merge($existingFieldHandle, $customSettingFieldHandles));

        if ((count($fieldHandles) <= 0) && $targetField === 'elementTitle') {
            return ['title'];
        }

        return $fieldHandles;
    }

    public function getExistingFieldHandle($fieldId): string
    {
        // A number represents a specific image field selected
        if (!preg_match('#^\d+$#', $fieldId)) {
            return '';
        }

        /** @var Field $optimizedImageFieldModel */
        $optimizedImageFieldModel = Craft::$app->getFields()->getFieldById($fieldId);

        return $optimizedImageFieldModel->handle ?? '';
    }

    /**
     * Parses a custom Element Metadata field setting and returns tags used as an array of names
     */
    public function getCustomSettingFieldHandles($value): array
    {
        // If there are no dynamic tags, just return the template
        if (!str_contains($value, '{')) {
            return [];
        }

        /**
         *  {           - our pattern starts with an open bracket
         *  <space>?    - zero or one space
         *  (object\.)? - zero or one characters that spell "object."
         *  (?<handles> - begin capture pattern and name it 'handles'
         *  [a-zA-Z_]*  - any number of characters in Craft field handles
         *  )           - end capture pattern named 'handles'
         */
        preg_match_all('#{ ?(object\.)?(?<handles>[a-zA-Z_]*)#', $value, $matches);

        $matchedHandlesCount = isset($matches['handles'])
            ? count($matches['handles'])
            : 0;

        if ($matchedHandlesCount > 0) {
            // Remove empty array items and make sure we only return each value once
            return array_filter(array_unique($matches['handles']));
        }

        return [];
    }

    public function getDescriptionLength(): int
    {
        $settings = MetaModule::getInstance()->getSettings();

        return $settings->maxMetaDescriptionLength ?: 160;
    }

    public function getMetadataFieldCount(): int|string
    {
        return (new Query())
            ->select(['id'])
            ->from([Table::FIELDS])
            ->where(['type' => ElementMetadataField::class])
            ->count();
    }
}
