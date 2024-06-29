<?php

namespace BarrelStrength\Sprout\mailer\components\elements\email\conditions;

use BarrelStrength\Sprout\mailer\components\elements\email\EmailElement;
use BarrelStrength\Sprout\mailer\components\elements\email\EmailElementQuery;
use BarrelStrength\Sprout\mailer\emailtypes\EmailTypeHelper;
use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;

class EmailTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('sprout-module-mailer', 'Email Type');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['emailTypeUid'];
    }

    protected function options(): array
    {
        return EmailTypeHelper::getEmailTypesOptions();
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EmailElementQuery $query */
        $query->andWhere(Db::parseParam('[[sprout_emails.emailTypeUid]]', $this->paramValue()));
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var EmailElement $element */
        return in_array($element->emailTypeUid, $this->getValues(), true);
    }
}
