<?php

namespace BarrelStrength\Sprout\forms\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * @role temporary: Craft 4 => 5
 * @schema sprout-module-forms
 * @deprecated Remove in craftcms/cms:6.0
 */
class m211101_000002_update_element_types extends Migration
{
    public function safeUp(): void
    {
        $types = [
            [
                'oldType' => 'barrelstrength\sproutforms\elements\Form',
                'newType' => 'BarrelStrength\Sprout\forms\components\elements\FormElement',
            ],
            [
                'oldType' => 'barrelstrength\sproutforms\elements\Entry',
                'newType' => 'Barrelstrength\Sprout\forms\components\elements\SubmissionElement',
            ],
        ];

        foreach ($types as $type) {
            $this->update(Table::ELEMENTS, [
                'type' => $type['newType'],
            ], ['type' => $type['oldType']], [], false);
        }
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
