<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Forms;

use Clesson\Silverstripe\Dashboard\Panels\DashboardPanel;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;

/**
 * A full-width dashboard form field that renders a React-powered panel grid.
 *
 * This field takes the full width of the CMS form (no label wrapper)
 * and bootstraps a React application via data attributes on a container div.
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Forms
 */
class DashboardField extends FormField
{
    /**
     * The dashboard key identifying which dashboard to display.
     */
    private string $dashboardKey;

    /**
     * Default panel classes to create for new users.
     *
     * @var string[]
     */
    private array $defaultPanels = [];

    /**
     * Creates a new DashboardField.
     *
     * @param string $name Field name
     * @param string $dashboardKey Identifier for the dashboard (e.g. 'trade-manager')
     */
    public function __construct(string $name, string $dashboardKey)
    {
        parent::__construct($name, '');
        $this->dashboardKey = $dashboardKey;
    }

    /**
     * Sets the default panel classes for new users.
     *
     * @param string[] $panelClasses Fully qualified class names of DashboardPanel subclasses
     * @return static
     */
    public function setDefaultPanels(array $panelClasses): static
    {
        $this->defaultPanels = $panelClasses;

        return $this;
    }

    /**
     * Returns the default panel classes.
     *
     * @return string[]
     */
    public function getDefaultPanels(): array
    {
        return $this->defaultPanels;
    }

    /**
     * Returns the dashboard key.
     *
     * @return string
     */
    public function getDashboardKey(): string
    {
        return $this->dashboardKey;
    }

    /**
     * Returns the API URL for loading the dashboard configuration.
     *
     * @return string
     */
    public function getConfigURL(): string
    {
        return Director::absoluteURL('dashboard-api/config?key=' . urlencode($this->dashboardKey));
    }

    /**
     * Returns the API URL for listing all available panels.
     *
     * @return string
     */
    public function getPanelsURL(): string
    {
        return Director::absoluteURL('dashboard-api/panels?key=' . urlencode($this->dashboardKey));
    }

    /**
     * Returns the API URL for saving the dashboard configuration.
     *
     * @return string
     */
    public function getSaveURL(): string
    {
        return Director::absoluteURL('dashboard-api/config');
    }

    /**
     * Renders the dashboard without the default label/holder wrapper.
     *
     * This ensures the dashboard takes the full width of the form,
     * similar to how LiteralField renders its content.
     *
     * @param array $properties
     * @return string
     */
    public function FieldHolder($properties = []): string
    {
        return $this->Field($properties);
    }

    /**
     * Renders the dashboard field and loads required assets.
     *
     * @param array $properties
     * @return string
     */
    public function Field($properties = []): string
    {
        $this->addDashboardAssets();

        $properties = array_merge($properties, [
            'DashboardKey' => $this->dashboardKey,
            'ConfigURL' => $this->getConfigURL(),
            'PanelsURL' => $this->getPanelsURL(),
            'SaveURL' => $this->getSaveURL(),
            'LabelManagePanels' => _t(__CLASS__ . '.MANAGE_PANELS', 'Manage panels'),
            'LabelClose' => _t(__CLASS__ . '.CLOSE', 'Close'),
            'LabelSingle' => _t(__CLASS__ . '.WIDTH_SINGLE', 'Single'),
            'LabelDouble' => _t(__CLASS__ . '.WIDTH_DOUBLE', 'Double'),
            'LabelLoading' => _t(__CLASS__ . '.LOADING', 'Loading…'),
            'LabelSettings' => _t(__CLASS__ . '.SETTINGS', 'Settings'),
            'LabelSave' => _t(__CLASS__ . '.SAVE', 'Save'),
        ]);

        return parent::Field($properties)->getValue();
    }

    /**
     * No-op: dashboard fields do not save data.
     *
     * @param mixed $record
     * @return void
     */
    public function saveInto($record): void
    {
        // Intentionally empty — display-only field
    }

    /**
     * Dashboard fields are always readonly by nature.
     *
     * @return FormField
     */
    public function performReadonlyTransformation(): FormField
    {
        return clone $this;
    }

    /**
     * Dashboard fields are always disabled-safe.
     *
     * @return FormField
     */
    public function performDisabledTransformation(): FormField
    {
        return clone $this;
    }

    /**
     * Loads the required JavaScript and CSS resources.
     *
     * Also discovers all registered DashboardPanel subclasses and loads
     * any panel-specific CSS/JS assets so that Entwine handlers
     * (e.g. Chart.js initialisation) are available when panel content
     * is injected via AJAX.
     *
     * @return void
     */
    protected function addDashboardAssets(): void
    {
        Requirements::javascript('clesson-de/silverstripe-dashboard:client/admin/dist/bundle.js');
        Requirements::css('clesson-de/silverstripe-dashboard:client/admin/dist/bundle.css');

        $this->addPanelAssets();
    }

    /**
     * Discovers all DashboardPanel subclasses and loads their CSS/JS assets.
     *
     * @return void
     */
    private function addPanelAssets(): void
    {
        $subclasses = ClassInfo::subclassesFor(DashboardPanel::class, false);

        foreach ($subclasses as $class) {
            /** @var DashboardPanel $panel */
            $panel = Injector::inst()->create($class);

            $css = $panel->getCSS();
            if ($css !== null) {
                Requirements::css($css);
            }

            $js = $panel->getJavaScript();
            if ($js !== null) {
                Requirements::javascript($js);
            }
        }
    }
}

