<?php

namespace BarrelStrength\Sprout\transactional\components\notificationevents;

use BarrelStrength\Sprout\transactional\notificationevents\BaseElementNotificationEvent;
use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\users\UserCondition;
use craft\elements\User;
use craft\elements\User as UserElement;
use craft\web\User as UserComponent;
use yii\base\Event;
use yii\web\UserEvent;

/**
 * @property UserEvent $event
 */
class UserLoggedInNotificationEvent extends BaseElementNotificationEvent
{
    public static function displayName(): string
    {
        return Craft::t('sprout-module-transactional', 'When a user logs in');
    }

    public function getDescription(): string
    {
        return Craft::t('sprout-module-transactional', 'Triggered when a user logs in.');
    }

    public static function conditionType(): string
    {
        return UserCondition::class;
    }

    public static function elementType(): string
    {
        return UserElement::class;
    }

    public static function getEventClassName(): ?string
    {
        return UserComponent::class;
    }

    public static function getEventName(): ?string
    {
        return UserComponent::EVENT_AFTER_LOGIN;
    }

    public function getTipHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('sprout-module-transactional/_components/notificationevents/user-event-info.md');
    }

    public function getEventVariables(): array
    {
        return [
            'user' => $this->event->identity,
        ];
    }

    public function getMockEventVariables(): array
    {
        $user = Craft::$app->getUser()->getIdentity();

        if ($condition = $this->condition) {
            $query = $condition->elementType::find();
            $condition->modifyQuery($query);
            $user = $query->one();
        }

        return [
            'user' => $user,
        ];
    }

    /**
     * Overrides default because the UserEvent is not an ElementEvent
     * but includes the UserElement where we apply our condition rules
     */
    public function matchNotificationEvent(Event $event): bool
    {
        if (!$event instanceof UserEvent) {
            return false;
        }

        /** @var User|ElementInterface $user */
        $user = $event->identity;

        return $this->matchElement($user);
    }
}
