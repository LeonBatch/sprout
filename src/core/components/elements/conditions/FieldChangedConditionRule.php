<?php

namespace BarrelStrength\Sprout\core\components\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

class FieldChangedConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('sprout-module-core', 'Field Changed');
    }

    public function getExclusiveQueryParams(): array
    {
        return $this->getValues();
    }

    protected function options(): array
    {
        $fields = Craft::$app->getFields()->getAllFields('global');

        return array_map(static function($field) {
            return [
                'label' => $field->name,
                'value' => $field->handle,
            ];
        }, $fields);
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        // No changes
    }

    public function matchElement(ElementInterface $element): bool
    {
        foreach ($this->getValues() as $fieldHandle) {
            if ($element->isFieldDirty($fieldHandle)) {
                return true;
            }
        }

        return false;
    }
}
