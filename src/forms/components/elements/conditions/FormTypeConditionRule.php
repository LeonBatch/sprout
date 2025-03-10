<?php

namespace BarrelStrength\Sprout\forms\components\elements\conditions;

use BarrelStrength\Sprout\forms\components\elements\db\FormElementQuery;
use BarrelStrength\Sprout\forms\components\elements\FormElement;
use BarrelStrength\Sprout\forms\formtypes\FormTypeHelper;
use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;

class FormTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('sprout-module-forms', 'Form Type');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['formTypeUid'];
    }

    protected function options(): array
    {
        $formTypes = FormTypeHelper::getFormTypes();

        return array_map(static function($formType) {
            return [
                'label' => $formType->name,
                'value' => $formType->uid,
            ];
        }, $formTypes);
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var FormElementQuery $query */
        $query->andWhere(Db::parseParam('[[sprout_forms.formTypeUid]]', $this->paramValue()));
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var FormElement $element */
        return in_array($element->formTypeUid, $this->getValues(), true);
    }
}
