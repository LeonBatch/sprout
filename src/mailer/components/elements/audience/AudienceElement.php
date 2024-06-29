<?php

namespace BarrelStrength\Sprout\mailer\components\elements\audience;

use BarrelStrength\Sprout\mailer\audience\AudienceType;
use BarrelStrength\Sprout\mailer\components\elements\audience\conditions\AudienceCondition;
use BarrelStrength\Sprout\mailer\components\elements\audience\fieldlayoutelements\AudienceHandleField;
use BarrelStrength\Sprout\mailer\components\elements\audience\fieldlayoutelements\AudienceNameField;
use BarrelStrength\Sprout\mailer\components\elements\audience\fieldlayoutelements\AudienceSettingsField;
use BarrelStrength\Sprout\mailer\MailerModule;
use BarrelStrength\Sprout\mailer\subscriberlists\SubscriptionRecord;
use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\SetStatus;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\SlugValidator;
use craft\web\CpScreenResponseBehavior;
use yii\web\Response;

/**
 * @property mixed $listType
 */
class AudienceElement extends Element
{
    public ?string $name = null;

    public ?string $handle = null;

    public ?string $type = null;

    public array $settings = [];

    public function __construct($config = [])
    {
        $this->settings = Json::decodeIfJson($config['settings'] ?? []);

        parent::__construct($config);
    }

    public static function displayName(): string
    {
        return Craft::t('sprout-module-mailer', 'Audience');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('sprout-module-mailer', 'audience');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('sprout-module-mailer', 'Audiences');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('sprout-module-mailer', 'audiences');
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function defineNativeFields(DefineFieldLayoutFieldsEvent $event): void
    {
        /** @var FieldLayout $fieldLayout */
        $fieldLayout = $event->sender;

        if ($event->sender->type !== self::class || $fieldLayout->type !== self::class) {
            return;
        }

        $event->fields[] = AudienceNameField::class;
        $event->fields[] = AudienceHandleField::class;
        $event->fields[] = AudienceSettingsField::class;
    }

    public static function find(): AudienceElementQuery
    {
        return new AudienceElementQuery(static::class);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(AudienceCondition::class, [static::class]);
    }

    public static function defaultTableAttributes(string $source): array
    {
        return [
            'type',
            'manage',
        ];
    }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('sprout-module-mailer', 'All audiences'),
            ],
        ];

        $sources[] = [
            'heading' => Craft::t('sprout-module-mailer', 'Audience Type'),
        ];

        $audienceTypes = MailerModule::getInstance()->audiences->getAudienceTypes();

        foreach ($audienceTypes as $audienceType) {
            $key = 'type:' . $audienceType;

            $sources[] = [
                'key' => $key,
                'label' => Craft::t('sprout-module-mailer', $audienceType::displayName()),
                'criteria' => ['type' => $audienceType],
            ];
        }

        return $sources;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'name' => Craft::t('sprout-module-mailer', 'Name'),
            [
                'label' => Craft::t('sprout-module-mailer', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            'id' => Craft::t('sprout-module-mailer', 'ID'),
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'handle' => ['label' => Craft::t('sprout-module-mailer', 'Audience Handle')],
            'view' => ['label' => Craft::t('sprout-module-mailer', 'View Subscribers')],
            'manage' => ['label' => Craft::t('sprout-module-mailer', 'Manage')],
            'id' => ['label' => Craft::t('sprout-module-mailer', 'ID')],
            'uid' => ['label' => Craft::t('sprout-module-mailer', 'UID')],
            'dateCreated' => ['label' => Craft::t('sprout-module-mailer', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('sprout-module-mailer', 'Date Updated')],
        ];
    }

    protected static function defineActions(string $source = null): array
    {
        $actions = parent::defineActions($source);

        $actions[] = SetStatus::class;
        $actions[] = Delete::class;

        return $actions;
    }

    public function getAudienceType(): AudienceType
    {
        $audience = new $this->type();
        $audience->element = $this;

        if ($this->settings) {
            $audience->setAttributes($this->settings, false);
        }

        return $audience;
    }

    /**
     * Use the name as the string representation.
     */
    public function __toString(): string
    {
        return $this->name ?? Craft::t('sprout-module-mailer', 'Audience with no name');
    }

    protected function statusFieldHtml(): string
    {
        $statusField = Cp::lightswitchFieldHtml([
            'id' => 'enabled',
            'label' => Craft::t('app', 'Enabled'),
            'name' => 'enabled',
            'on' => $this->enabled,
            'disabled' => $this->getIsRevision(),
            'status' => $this->getAttributeStatus('enabled'),
        ]);

        $statusHtml = Html::tag('div', $statusField, ['class' => 'meta']);

        return $statusHtml;
    }

    public function cpEditUrl(): ?string
    {
        $path = UrlHelper::cpUrl('sprout/email/audiences/edit/' . $this->id);

        $params = [];

        if (Craft::$app->getIsMultiSite()) {
            $params['site'] = $this->getSite()->handle;
        }

        return UrlHelper::cpUrl($path, $params);
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('sprout/email/audiences');
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        $crumbs = [
            [
                'label' => Craft::t('sprout-module-mailer', 'Email'),
                'url' => UrlHelper::url('sprout/email'),
            ],
            [
                'label' => Craft::t('sprout-module-mailer', 'Audience'),
                'url' => UrlHelper::url('sprout/email/audiences'),
            ],
        ];

        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs($crumbs);

        // This is easier than creating a custom Element Edit template
        // like for User Elements when all we want is to disable the
        // revisions dropdown that isn't appropriate for this use case
        // Another solution could be requesting support for
        // $response->showDrafts = true (or something like this)
        // So a custom element can disable draft details in the UI
        // when only using drafts for the initial Element creation step.
        Craft::$app->getView()->registerCss('
            .context-btngroup {
                display: none;
            }
        ', [], 'context-btn-no-drafts-hack');
    }

    public function getAttributeHtml(string $attribute): string
    {
        $audienceType = $this->getAudienceType();

        switch ($attribute) {
            case 'handle':
                return '<code>' . $this->handle . '</code>';

            case 'manage':

                return $audienceType->getColumnAttributeHtml();
        }

        return parent::getAttributeHtml($attribute);
    }

    public function getFieldLayout(): ?FieldLayout
    {
        return Craft::$app->getFields()->getLayoutByType(static::class);
    }

    public function afterSave(bool $isNew): void
    {
        // Get the list record
        if (!$isNew) {
            $record = AudienceElementRecord::findOne($this->id);

            if (!$record instanceof AudienceElementRecord) {
                throw new ElementNotFoundException('Invalid audience ID: ' . $this->id);
            }
            //            $record->elementId = $this->elementId;
        } else {
            $record = new AudienceElementRecord();
            $record->id = $this->id;
        }

        $record->name = $this->name;
        $record->handle = $this->handle;
        $record->type = $this->type;
        $record->settings = Json::encode($this->settings);

        $record->save(false);

        // Update the entry's descendants, who may be using this entry's URI in their own URIs
        Craft::$app->getElements()->updateElementSlugAndUri($this);

        parent::afterSave($isNew);
    }

    public function canView(User $user): bool
    {
        return $user->can(MailerModule::p('editAudiences'));
    }

    public function canSave(User $user): bool
    {
        return $user->can(MailerModule::p('editAudiences'));
    }

    public function canDelete(User $user): bool
    {
        return $user->can(MailerModule::p('editAudiences'));
    }

    public function canDuplicate(User $user): bool
    {
        return false;
    }

    protected function metadata(): array
    {
        /** @var AudienceType|string $audienceType */
        $audienceType = $this->type;

        return [
            Craft::t('sprout-module-mailer', 'Audience Type') => $audienceType::displayName(),
        ];
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name'], 'required', 'except' => self::SCENARIO_ESSENTIALS];
        $rules[] = [['handle'], 'required', 'except' => self::SCENARIO_ESSENTIALS];
        $rules[] = [['type'], 'safe'];
        $rules[] = [['settings'], 'safe'];

        $rules[] = [
            ['handle'],
            SlugValidator::class,
            'except' => self::SCENARIO_ESSENTIALS,
        ];

        return $rules;
    }

    public function isSubscribed(mixed $identifier): bool
    {
        $criteria['subscriberListId'] = $this->id;

        if ($identifier instanceof User) {
            $criteria['userId'] = $identifier->id;
        } elseif (is_numeric($identifier)) {
            $criteria['userId'] = $identifier;
        } elseif (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::find()
                ->email($identifier)
                ->one();

            if ($user === null) {
                return false;
            }

            $criteria['userId'] = $user->id;
        } else {
            return false;
        }

        return SubscriptionRecord::find()
            ->where($criteria)
            ->exists();
    }
}
