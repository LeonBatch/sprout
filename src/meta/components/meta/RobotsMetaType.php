<?php

namespace BarrelStrength\Sprout\meta\components\meta;

use BarrelStrength\Sprout\meta\metadata\MetaType;
use BarrelStrength\Sprout\meta\MetaModule;
use Craft;
use craft\base\Field;

class RobotsMetaType extends MetaType
{
    protected string|array|null $robots = null;

    public static function displayName(): string
    {
        return Craft::t('sprout-module-meta', 'Robots');
    }

    public function attributes(): array
    {
        $attributes = parent::attributes();
        $attributes[] = 'robots';

        return $attributes;
    }

    public function getRobots()
    {
        if ($this->robots || $this->metadata->getRawDataOnly()) {
            return $this->robots;
        }

        if ($this->robots !== null) {
            return MetaModule::getInstance()->optimizeMetadata->prepareRobotsMetadataValue($this->robots);
        }

        return MetaModule::getInstance()->optimizeMetadata->globals['robots'] ?? null;
    }

    public function setRobots($value): void
    {
        $this->robots = $value;
    }

    public function getHandle(): string
    {
        return 'robots';
    }

    public function getIconPath(): string
    {
        return '@Sprout/Assets/dist/static/meta/icons/search-minus.svg';
    }

    public function getSettingsHtml(Field $field): string
    {
        $robotsNamespace = $field->handle . '[metadata][robots]';
        $robots = MetaModule::getInstance()->optimizeMetadata->prepareRobotsMetadataForSettings($this->robots);

        return Craft::$app->getView()->renderTemplate('sprout-module-meta/_components/fields/ElementMetadata/blocks/robots.twig', [
            'meta' => $this,
            'field' => $field,
            'robotsNamespace' => $robotsNamespace,
            'robots' => $robots,
        ]);
    }

    public function showMetaDetailsTab(): bool
    {
        return MetaModule::getInstance()->optimizeMetadata->elementMetadataField->showRobots;
    }

    public function getMetaTagData(): array
    {
        $tagData = parent::getMetaTagData();

        if (is_array($tagData['robots'])) {
            $tagData['robots'] = MetaModule::getInstance()->optimizeMetadata->prepareRobotsMetadataValue($tagData['robots']);
        }

        return $tagData;
    }
}
