<?php

namespace BarrelStrength\Sprout\transactional\components\elements\conditions;

use BarrelStrength\Sprout\core\twig\TemplateHelper;
use BarrelStrength\Sprout\mailer\components\elements\email\EmailElement;
use BarrelStrength\Sprout\transactional\components\elements\TransactionalEmailElementQuery;
use BarrelStrength\Sprout\transactional\components\emailvariants\TransactionalEmailVariant;
use BarrelStrength\Sprout\transactional\TransactionalModule;
use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

class NotificationEventConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('sprout-module-transactional', 'Notification Event');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['emailVariantSettings'];
    }

    protected function options(): array
    {
        $events = TransactionalModule::getInstance()->notificationEvents->getNotificationEventTypes();

        return TemplateHelper::optionsFromComponentTypes($events);
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $operator = $this->operator === 'in' ? 'in' : 'not in';

        /** @var TransactionalEmailElementQuery $query */
        $query->notificationEventFilterRule = [
            'operator' => $operator,
            'values' => $this->getValues(),
        ];
    }

    public function matchElement(ElementInterface $element): bool
    {
        if (!$element instanceof EmailElement) {
            return false;
        }

        /** @var TransactionalEmailVariant $emailVariantSettings */
        $emailVariantSettings = $element->getEmailVariant();
        $notificationEvent = $emailVariantSettings->getNotificationEvent($element);

        return $this->matchValue($notificationEvent::class);
    }
}
