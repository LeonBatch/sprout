<?php

namespace BarrelStrength\Sprout\redirects\components\elements\conditions;

use BarrelStrength\Sprout\redirects\components\elements\db\RedirectElementQuery;
use BarrelStrength\Sprout\redirects\components\elements\RedirectElement;
use BarrelStrength\Sprout\redirects\redirects\MatchStrategy;
use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

class MatchStrategyConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('sprout-module-redirects', 'Match Strategy');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['matchStrategy'];
    }

    protected function options(): array
    {
        return MatchStrategy::options();
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var RedirectElementQuery $query */
        $query->matchStrategy($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var RedirectElement $element */
        return $this->matchValue($element->matchStrategy);
    }
}
