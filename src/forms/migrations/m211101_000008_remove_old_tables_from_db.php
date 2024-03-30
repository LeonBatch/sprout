<?php

namespace BarrelStrength\Sprout\forms\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;

class m211101_000008_remove_old_tables_from_db extends Migration
{
    public const SPROUT_KEY = 'sprout';

    public const MODULE_ID = 'sprout-module-forms';

    public const OLD_FORM_GROUPS_TABLE = '{{%sproutforms_formgroups}}';
    public const OLD_FORMS_TABLE = '{{%sproutforms_forms}}';
    public const OLD_FORM_INTEGRATIONS_TABLE = '{{%sproutforms_integrations}}';
    public const OLD_FORM_INTEGRATIONS_LOG_TABLE = '{{%sproutforms_integrations_log}}';
    public const OLD_SUBMISSIONS_TABLE = '{{%sproutforms_entries}}';
    public const OLD_FORM_RULES_TABLE = '{{%sproutforms_rules}}';
    public const OLD_FORM_SUBMISSIONS_SPAM_LOG_TABLE = '{{%sproutforms_entries_spam_log}}';
    public const OLD_FORM_SUBMISSIONS_STATUSES_TABLE = '{{%sproutforms_entrystatuses}}';

    public function safeUp(): void
    {
        $moduleSettingsKey = self::SPROUT_KEY . '.' . self::MODULE_ID;

        Craft::$app->getProjectConfig()->remove($moduleSettingsKey.'.trackRemoteIp');
        Craft::$app->getProjectConfig()->remove($moduleSettingsKey.'.saveDataByDefault');
        Craft::$app->getProjectConfig()->remove($moduleSettingsKey.'.enableSaveDataDefaultValue');
        Craft::$app->getProjectConfig()->remove($moduleSettingsKey.'.enablePayloadForwarding');
        Craft::$app->getProjectConfig()->remove($moduleSettingsKey.'.pluginNameOverride');
        Craft::$app->getProjectConfig()->remove($moduleSettingsKey.'.formTypeUid');

        Db::dropAllForeignKeysToTable(self::OLD_FORMS_TABLE);
        Db::dropAllForeignKeysToTable(self::OLD_FORM_INTEGRATIONS_TABLE);
        Db::dropAllForeignKeysToTable(self::OLD_FORM_INTEGRATIONS_LOG_TABLE);
        Db::dropAllForeignKeysToTable(self::OLD_FORM_RULES_TABLE);
        Db::dropAllForeignKeysToTable(self::OLD_SUBMISSIONS_TABLE);
        Db::dropAllForeignKeysToTable(self::OLD_FORM_SUBMISSIONS_SPAM_LOG_TABLE);

        $this->dropTableIfExists(self::OLD_FORMS_TABLE);
        $this->dropTableIfExists(self::OLD_FORM_GROUPS_TABLE);
        $this->dropTableIfExists(self::OLD_FORM_INTEGRATIONS_TABLE);
        $this->dropTableIfExists(self::OLD_FORM_INTEGRATIONS_LOG_TABLE);
        $this->dropTableIfExists(self::OLD_FORM_RULES_TABLE);

        $this->dropTableIfExists(self::OLD_SUBMISSIONS_TABLE);
        $this->dropTableIfExists(self::OLD_FORM_SUBMISSIONS_SPAM_LOG_TABLE);
        $this->dropTableIfExists(self::OLD_FORM_SUBMISSIONS_STATUSES_TABLE);
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
