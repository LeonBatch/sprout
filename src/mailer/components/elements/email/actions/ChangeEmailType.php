<?php

namespace BarrelStrength\Sprout\mailer\components\elements\email\actions;

use Craft;
use craft\base\ElementAction;

class ChangeEmailType extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('sprout-module-mailer', 'Change Email Type');
    }

    public static function isDestructive(): bool
    {
        return true;
    }

    /**
     * @noinspection UnterminatedStatementJS UnterminatedStatementJS
     * @noinspection JSUnnecessarySemicolon JSUnnecessarySemicolon
     */
    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        validateSelection: \$selectedItems => Garnish.hasAttr(\$selectedItems.find('.element'), 'data-savable'),
        activate: \$selectedItems => {
            const elementIds = \$selectedItems.map((index, element) => {
                return $(element).data('id');
            }).get();
            const slideout = new  Craft.CpScreenSlideout('sprout-module-mailer/email-types/change-email-type-slideout', {
                params: {
                    elementIds: elementIds,
                },
            });
            slideout.on('submit', function() {
                Craft.elementIndex.updateElements();
            });
        },
    });
})();
JS, [static::class]);

        return null;
    }
}
