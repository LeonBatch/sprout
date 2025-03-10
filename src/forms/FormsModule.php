<?php

namespace BarrelStrength\Sprout\forms;

use BarrelStrength\Sprout\core\db\MigrationInterface;
use BarrelStrength\Sprout\core\db\MigrationTrait;
use BarrelStrength\Sprout\core\editions\EditionTrait;
use BarrelStrength\Sprout\core\modules\Settings;
use BarrelStrength\Sprout\core\modules\SettingsHelper;
use BarrelStrength\Sprout\core\modules\SproutModuleInterface;
use BarrelStrength\Sprout\core\modules\SproutModuleTrait;
use BarrelStrength\Sprout\core\modules\TranslatableTrait;
use BarrelStrength\Sprout\core\relations\RelationsHelper;
use BarrelStrength\Sprout\core\Sprout;
use BarrelStrength\Sprout\core\twig\SproutVariable;
use BarrelStrength\Sprout\datastudio\datasources\DataSources;
use BarrelStrength\Sprout\fields\FieldsModule;
use BarrelStrength\Sprout\forms\captchas\Captchas;
use BarrelStrength\Sprout\forms\components\datasources\SpamLogDataSource;
use BarrelStrength\Sprout\forms\components\datasources\SubmissionsDataSource;
use BarrelStrength\Sprout\forms\components\elements\FormElement;
use BarrelStrength\Sprout\forms\components\elements\SubmissionElement;
use BarrelStrength\Sprout\forms\components\emailtypes\FormSummaryEmailType;
use BarrelStrength\Sprout\forms\components\fields\FormsRelationField;
use BarrelStrength\Sprout\forms\components\fields\SubmissionsRelationField;
use BarrelStrength\Sprout\forms\components\formfeatures\WorkflowTabFormFeature;
use BarrelStrength\Sprout\forms\components\notificationevents\SaveSubmissionNotificationEvent;
use BarrelStrength\Sprout\forms\controllers\SubmissionsController;
use BarrelStrength\Sprout\forms\formfields\FormFields;
use BarrelStrength\Sprout\forms\formfields\FrontEndFields;
use BarrelStrength\Sprout\forms\forms\Forms;
use BarrelStrength\Sprout\forms\forms\FormsHelper;
use BarrelStrength\Sprout\forms\forms\FormsVariable;
use BarrelStrength\Sprout\forms\forms\Submissions;
use BarrelStrength\Sprout\forms\forms\SubmissionStatuses;
use BarrelStrength\Sprout\forms\formtypes\FormTypeHelper;
use BarrelStrength\Sprout\forms\formtypes\FormTypes;
use BarrelStrength\Sprout\forms\integrations\FormIntegrations;
use BarrelStrength\Sprout\forms\submissions\SubmissionsHelper;
use BarrelStrength\Sprout\mailer\emailtypes\EmailTypes;
use BarrelStrength\Sprout\transactional\notificationevents\NotificationEvents;
use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\models\FieldLayout;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;
use yii\base\Module;

/**
 * @property Forms $forms
 * @property FormFields $formFields
 * @property Submissions $submissions
 * @property SubmissionStatuses $submissionStatuses
 * @property FrontEndFields $frontEndFields
 * @property FormIntegrations $formIntegrations
 * @property FormTypes $formTypes
 * @property Captchas $captchas
 */
class FormsModule extends Module implements SproutModuleInterface, MigrationInterface
{
    use SproutModuleTrait;
    use EditionTrait;
    use MigrationTrait;
    use TranslatableTrait;

    public static function getInstance(): FormsModule
    {
        FieldsModule::getInstance();

        /** @var FormsModule $module */
        $module = Sprout::getSproutModule(static::class, 'sprout-module-forms');

        return $module;
    }

    public static function getDisplayName(): string
    {
        $displayName = Craft::t('sprout-module-core', 'Forms');

        return $displayName;
    }

    public static function getShortName(): string
    {
        return 'forms';
    }

    public static function getDescription(): string
    {
        return Craft::t('sprout-module-core', 'Form builder and submission management');
    }

    public static function getUpgradeMessage(): string
    {
        return Craft::t('sprout-module-core', 'Upgrade to Sprout Forms PRO to manage Unlimited Forms');
    }

    public function init(): void
    {
        parent::init();

        $this->registerTranslations();

        $this->setComponents([
            'forms' => Forms::class,
            'formFields' => FormFields::class,
            'submissions' => Submissions::class,
            'submissionStatuses' => SubmissionStatuses::class,
            'frontEndFields' => FrontEndFields::class,
            //'formIntegrations' => FormIntegrations::class,
            'formTypes' => FormTypes::class,
            'captchas' => Captchas::class,
        ]);

        Craft::setAlias('@BarrelStrength/Sprout/forms', __DIR__);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event): void {
                $event->rules = array_merge($event->rules, $this->getCpUrlRules());
            });

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e): void {
                $e->roots['sprout-module-forms'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            });

        Event::on(
            Settings::class,
            Settings::INTERNAL_SPROUT_EVENT_REGISTER_CP_SETTINGS_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event): void {
                $groupName = Craft::t('sprout-module-forms', 'Forms');
                $event->navItems[$groupName] = $this->getSproutCpSettingsNavItems();
            });

        Event::on(
            Settings::class,
            Settings::INTERNAL_SPROUT_EVENT_REGISTER_CRAFT_CP_SIDEBAR_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event): void {
                $event->navItems[] = $this->getCraftCpSidebarNavItems();
            });

        Event::on(
            Settings::class,
            Settings::INTERNAL_SPROUT_EVENT_REGISTER_CRAFT_CP_SETTINGS_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event): void {
                $event->navItems['sprout-module-forms'] = $this->getCraftCpSettingsNavItems();
            });

        Event::on(
            SproutVariable::class,
            SproutVariable::EVENT_INIT,
            function(Event $event): void {
                $event->sender->registerModule($this);
                $event->sender->registerVariable('forms', new FormsVariable());
            });

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event): void {
                $event->permissions[] = [
                    'heading' => Craft::t('sprout-module-forms', 'Sprout Module | Forms'),
                    'permissions' => $this->getUserPermissions(),
                ];
            });

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = SubmissionsRelationField::class;
                $event->types[] = FormsRelationField::class;
            });

        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = FormElement::class;
                $event->types[] = SubmissionElement::class;
            }
        );

        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_BEHAVIORS,
            [SubmissionsHelper::class, 'attachSubmissionElementFieldLayoutBehavior']
        );

        Event::on(
            DataSources::class,
            DataSources::INTERNAL_SPROUT_EVENT_REGISTER_DATA_SOURCES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = SubmissionsDataSource::class;
                //$event->types[] = IntegrationLogDataSource::class;
                $event->types[] = SpamLogDataSource::class;
            });

        Event::on(
            EmailTypes::class,
            EmailTypes::EVENT_REGISTER_EMAIL_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = FormSummaryEmailType::class;
            });

        Event::on(
            NotificationEvents::class,
            NotificationEvents::INTERNAL_SPROUT_EVENT_REGISTER_NOTIFICATION_EVENTS,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = SaveSubmissionNotificationEvent::class;
            });

        Event::on(
            RelationsHelper::class,
            RelationsHelper::EVENT_REGISTER_SOURCE_RELATIONS_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = FormElement::class;
                $event->types[] = SubmissionElement::class;
            }
        );

        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_NATIVE_FIELDS,
            [FormTypeHelper::class, 'defineNativeFieldsPerFormType']);

        Event::on(
            SubmissionsController::class,
            SubmissionsController::EVENT_BEFORE_VALIDATE,
            [Captchas::class, 'handleValidateCaptchas']);

        Event::on(
            NotificationEvents::class,
            NotificationEvents::EVENT_REGISTER_NOTIFICATION_EVENT_RELATIONS_TYPES,
            [FormsHelper::class, 'registerNotificationEventRelationsTypes']);

        Event::on(
            DataSources::class,
            DataSources::EVENT_REGISTER_DATA_SOURCE_RELATIONS_TYPES,
            [FormsHelper::class, 'registerDataSourceRelationsTypes']);

        Event::on(
            FormElement::class,
            FormElement::INTERNAL_SPROUT_EVENT_REGISTER_FORM_FEATURE_TABS,
            [WorkflowTabFormFeature::class, 'registerWorkflowTab']
        );

        //Event::on(
        //    FormTypesController::class,
        //    FormTypesController::INTERNAL_SPROUT_EVENT_DEFINE_FORM_FEATURE_SETTINGS,
        //    [WorkflowTabFormFeature::class, 'defineFormTypeSettings']
        //);

        $this->registerProjectConfigEventListeners();
    }

    public function createSettingsModel(): FormsSettings
    {
        return new FormsSettings();
    }

    public function getSettings(): FormsSettings
    {
        /** @var FormsSettings $settings */
        $settings = SettingsHelper::getSettingsConfig($this, FormsSettings::class);

        return $settings;
    }

    public function getUserPermissions(): array
    {
        return [
            self::p('editForms') => [
                'label' => Craft::t('sprout-module-forms', 'Edit Forms'),
                //'nested' => [
                //    self::p('editIntegrations') => [
                //        'label' => Craft::t('sprout-module-forms', 'Edit Integrations'),
                //    ],
                //],
            ],
            self::p('viewSubmissions') => [
                'label' => Craft::t('sprout-module-forms', 'View Submissions'),
                'nested' => [
                    self::p('editSubmissions') => [
                        'label' => Craft::t('sprout-module-forms', 'Edit Submissions'),
                    ],
                ],
            ],
        ];
    }

    protected function getCraftCpSidebarNavItems(): array
    {
        if (!Craft::$app->getUser()->checkPermission(self::p('accessModule'))) {
            return [];
        }

        $settings = $this->getSettings();

        $navItems = [];

        $navItems['forms'] = [
            'label' => Craft::t('sprout-module-forms', 'Forms'),
            'url' => 'sprout/forms/forms',
        ];

        $navItems['submissions'] = [
            'label' => Craft::t('sprout-module-forms', 'Submissions'),
            'url' => 'sprout/forms/submissions',
        ];

        if ($settings->defaultSidebarTab == 'submissions') {
            $navItems = array_reverse($navItems);
        }

        return [
            'group' => Craft::t('sprout-module-forms', 'Forms'),
            'icon' => self::svg('icons/icon-mask.svg'),
            'url' => 'sprout/forms',
            'navItems' => $navItems,
        ];
    }

    protected function getCraftCpSettingsNavItems(): array
    {
        return [
            'label' => self::getDisplayName(),
            'url' => 'sprout/settings/forms/form-types',
            'icon' => self::svg('icons/icon.svg'),
        ];
    }

    protected function getSproutCpSettingsNavItems(): array
    {
        return [
            'form-defaults' => [
                'label' => Craft::t('sprout-module-forms', 'Default Settings'),
                'url' => 'sprout/settings/general',
            ],
            'form-types' => [
                'label' => Craft::t('sprout-module-forms', 'Form Types'),
                'url' => 'sprout/settings/forms/form-types',
            ],
            //'integrations' => [
            //    'label' => Craft::t('sprout-module-forms', 'Integrations'),
            //    'url' => 'sprout/settings/forms/integrations',
            //],
            'spam-protection' => [
                'label' => Craft::t('sprout-module-forms', 'Spam Protection'),
                'url' => 'sprout/settings/forms/spam-protection',
            ],
            'submission-statuses' => [
                'label' => Craft::t('sprout-module-forms', 'Submission Statuses'),
                'url' => 'sprout/settings/forms/submission-statuses',
            ],
        ];
    }

    protected function getCpUrlRules(): array
    {
        return [
            'sprout/forms' =>
                'sprout-module-core/settings/redirect-nav-item',

            'sprout/forms/forms' =>
                'sprout-module-forms/forms/forms-index-template',
            'sprout/forms/forms/new' =>
                'sprout-module-forms/forms/new-form',
            'sprout/forms/forms/create' =>
                'sprout-module-forms/forms/create-form',
            'sprout/forms/forms/edit/<elementId:\d+>' =>
                'elements/edit',

            'sprout/forms/submissions' =>
                'sprout-module-forms/submissions/submissions-index-template',
            'sprout/forms/submissions/edit/<elementId:\d+>' =>
                'elements/edit',

            // Welcome
            'sprout/welcome/forms' => [
                'template' => 'sprout-module-forms/_admin/welcome',
            ],
            'sprout/upgrade/forms' => [
                'template' => 'sprout-module-forms/_admin/upgrade',
            ],

            // Settings: Form Types
            'sprout/settings/forms/form-types/new' =>
                'sprout-module-forms/form-types/edit',
            'sprout/settings/forms/form-types/edit/<formTypeUid:.*>' =>
                'sprout-module-forms/form-types/edit',
            'sprout/settings/forms/form-types' =>
                'sprout-module-forms/form-types/form-types-index-template',

            // Settings: Integration Types
            //'sprout/settings/forms/integrations/new' =>
            //    'sprout-module-forms/form-integration-settings/edit',
            //'sprout/settings/forms/integrations/edit/<integrationTypeUid:.*>' =>
            //    'sprout-module-forms/form-integration-settings/edit',
            //'sprout/settings/forms/integrations' =>
            //    'sprout-module-forms/form-integration-settings/form-integrations-index-template',

            // Settings
            'sprout/settings/general' => [
                'template' => 'sprout-module-forms/_settings/forms',
            ],
            'sprout/settings/forms/spam-protection' => [
                'template' => 'sprout-module-forms/_settings/spam-protection',
            ],
            'sprout/settings/forms/submission-statuses' => [
                'template' => 'sprout-module-forms/_settings/submission-statuses/index',
            ],
            'sprout/settings/forms/submission-statuses/<submissionStatusId:.*>' => [
                'template' => 'sprout-module-forms/_settings/submission-statuses/edit',
            ],
        ];
    }

    private function registerProjectConfigEventListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        // Submission Statuses
        //        $projectConfigService->onAdd($key, [self::projectConfigPath(), 'handleChangedFieldLayout'])
        //            ->onUpdate($key, [self::projectConfigPath(), 'handleChangedFieldLayout'])
        //            ->onRemove($key, [self::projectConfigPath(), 'handleDeletedFieldLayout']);

        //        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, static function(RebuildConfigEvent $event) {
        //            $event->config['commerce'] = ProjectConfigData::rebuildProjectConfig();
        //        });
    }
}
