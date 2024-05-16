<?php

namespace BarrelStrength\Sprout\mailer\migrations;

use Craft;
use craft\db\Migration;

class m211101_000001_update_lists_projectconfig extends Migration
{
    public const SPROUT_KEY = 'sprout';
    public const MODULE_ID = 'sprout-module-mailer';
    public const OLD_CONFIG_KEY = 'plugins.sprout-lists.settings';

    public function safeUp(): void
    {
        $moduleSettingsKey = self::SPROUT_KEY . '.' . self::MODULE_ID;

        $defaultSettings = [
            'enableAutoList' => false,
            'enableUserSync' => false,
        ];

        $oldConfig = Craft::$app->getProjectConfig()->get(self::OLD_CONFIG_KEY) ?? [];

        $newConfigExists = Craft::$app->getProjectConfig()->get($moduleSettingsKey);

        if (empty($oldConfig) && $newConfigExists) {
            return;
        }

        $newConfig = [];
        
        foreach ($defaultSettings as $key => $defaultValue) {
            $oldValue = isset($oldConfig[$key]) && !empty($oldConfig[$key]) ? $oldConfig[$key] : null;
            $newConfig[$key] = $oldValue ?? $defaultValue;
        }

        unset(
            $newConfig['enableAutoList'],
            $newConfig['enableUserSync']
        );

        Craft::$app->getProjectConfig()->set($moduleSettingsKey, $newConfig,
            "Update Sprout Settings for “{$moduleSettingsKey}”"
        );

        Craft::$app->getProjectConfig()->remove(self::OLD_CONFIG_KEY);

    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
