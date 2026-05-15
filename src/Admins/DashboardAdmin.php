<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Admins;

use Clesson\Silverstripe\Dashboard\Forms\DashboardField;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;

/**
 * A standalone CMS admin section that renders a full-page dashboard.
 *
 * This admin is **disabled by default**. To enable it, add the following to your
 * project's YAML configuration:
 *
 * ```yaml
 * Clesson\Silverstripe\Dashboard\Admins\DashboardAdmin:
 *   enabled: true
 *
 * SilverStripe\Admin\AdminRootController:
 *   default_panel: 'Clesson\Silverstripe\Dashboard\Admins\DashboardAdmin'
 * ```
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Admins
 */
class DashboardAdmin extends LeftAndMain
{
    /**
     * Whether this admin section is enabled and visible in the CMS menu.
     *
     * @config
     */
    private static bool $enabled = false;

    /**
     * The dashboard key used for storing per-user panel configurations.
     *
     * @config
     */
    private static string $dashboard_key = 'main';

    /**
     * @config
     */
    private static string $url_segment = 'dashboard';

    /**
     * @config
     */
    private static string $menu_title = 'Dashboard';

    /**
     * @config
     */
    private static float $menu_priority = 200;

    /**
     * @config
     */
    private static string $menu_icon_class = 'font-icon-dashboard';

    /**
     * @config
     */
    private static string $required_permission_codes = 'CMS_ACCESS_LEFTANDMAIN';

    /**
     * Allowed panel classes for this dashboard.
     *
     * If empty (default), all registered DashboardPanel subclasses are available.
     * Set via YAML config to restrict which panels appear.
     *
     * @config
     * @var string[]
     */
    private static array $allowed_panels = [];

    /**
     * Default panel classes to create for new users.
     *
     * If empty, falls back to allowed_panels or all panels.
     *
     * @config
     * @var string[]
     */
    private static array $default_panels = [];

    /**
     * Controls visibility of this admin in the CMS menu.
     *
     * Returns false when the admin is not enabled via config,
     * effectively hiding it from the CMS navigation.
     *
     * @param \SilverStripe\Security\Member|null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        if (!static::config()->get('enabled')) {
            return false;
        }

        return parent::canView($member);
    }

    /**
     * Returns the edit form containing the dashboard field.
     *
     * @param int|null $id Not used
     * @param FieldList|null $fields Not used
     * @return Form
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        $dashboardKey = static::config()->get('dashboard_key');
        $allowedPanels = static::config()->get('allowed_panels');
        $defaultPanels = static::config()->get('default_panels');

        /** @var DashboardField $dashboardField */
        $dashboardField = DashboardField::create('MainDashboard', $dashboardKey);

        if (!empty($defaultPanels)) {
            $dashboardField->setDefaultPanels($defaultPanels);
        }

        if (!empty($allowedPanels)) {
            $dashboardField->setAllowedPanels($allowedPanels);
        }

        $form = Form::create(
            $this,
            'EditForm',
            FieldList::create($dashboardField),
            FieldList::create()
        );

        $form->setHTMLID('Form_EditForm');
        $form->addExtraClass('cms-edit-form');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->addExtraClass('center ss-tabset cms-tabset ' . $this->BaseCSSClasses());
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        return $form;
    }
}


