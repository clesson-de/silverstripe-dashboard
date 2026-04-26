<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Panels;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

/**
 * Abstract base class for dashboard panels.
 *
 * Each panel provides a title, a resource URL for loading content,
 * and optional CSS/JS assets. Concrete implementations must define
 * the title and resource URL. Panels may also expose settings fields
 * that allow per-user configuration (e.g. number of entries to show).
 *
 * Every panel automatically includes an auto-refresh setting (in minutes).
 * Subclasses should override getSettingsFields() and call parent to keep it.
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Panels
 */
abstract class DashboardPanel
{
    /**
     * Current settings for this panel instance.
     *
     * @var array
     */
    private array $settings = [];

    /**
     * Returns the translated panel title.
     *
     * @return string
     */
    abstract public function getTitle(): string;

    /**
     * Returns the URL to load the panel content.
     *
     * @return string
     */
    abstract public function getResourceURL(): string;

    /**
     * Returns an optional CSS file URL for this panel.
     *
     * @return string|null
     */
    public function getCSS(): ?string
    {
        return null;
    }

    /**
     * Returns an optional JavaScript file URL for this panel.
     *
     * @return string|null
     */
    public function getJavaScript(): ?string
    {
        return null;
    }

    /**
     * Returns the default width of the panel.
     *
     * @return string 'single' or 'double'
     */
    public function getDefaultWidth(): string
    {
        return 'single';
    }

    /**
     * Returns the default auto-refresh interval in seconds.
     *
     * @return int 0 means no auto-refresh
     */
    public function getDefaultRefreshInterval(): int
    {
        return 0;
    }

    /**
     * Returns a unique identifier for this panel (the fully qualified class name).
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return static::class;
    }

    /**
     * Returns the settings fields for this panel.
     *
     * Includes an auto-refresh field by default. Override in subclasses
     * and call parent::getSettingsFields() to keep the refresh field.
     *
     * @return FieldList
     */
    public function getSettingsFields(): FieldList
    {
        /** @var NumericField $refreshField */
        $refreshField = NumericField::create(
            'refreshInterval',
            _t('Clesson\\Silverstripe\\Dashboard\\Panels\\DashboardPanel.REFRESH_INTERVAL', 'Auto-refresh (minutes)')
        )->setValue($this->getSetting('refreshInterval', 0));

        return FieldList::create($refreshField);
    }

    /**
     * Returns the default settings for this panel.
     *
     * Override in subclasses and merge with parent::getDefaultSettings().
     *
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return [
            'refreshInterval' => 0,
        ];
    }

    /**
     * Whether this panel has configurable settings.
     *
     * Always true since every panel has at least the refresh setting.
     *
     * @return bool
     */
    public function hasSettings(): bool
    {
        return true;
    }

    /**
     * Sets the current settings for this panel instance.
     *
     * @param array $settings
     * @return static
     */
    public function setSettings(array $settings): static
    {
        $this->settings = array_merge($this->getDefaultSettings(), $settings);

        return $this;
    }

    /**
     * Returns all current settings.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return array_merge($this->getDefaultSettings(), $this->settings);
    }

    /**
     * Returns a single setting value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->getSettings();

        return $settings[$key] ?? $default;
    }
}
