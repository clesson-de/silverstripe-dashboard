<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Dashboard\Controllers;

use Clesson\Silverstripe\Dashboard\Models\DashboardUserConfig;
use Clesson\Silverstripe\Dashboard\Panels\DashboardPanel;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * API controller for dashboard configuration management.
 *
 * Provides endpoints to load, save, and list dashboard panel configurations.
 * All endpoints require an authenticated user.
 *
 * Route (defined in _config/routes.yml):
 *   GET  /dashboard-api/config?key={dashboardKey}
 *   POST /dashboard-api/config
 *   GET  /dashboard-api/panels?key={dashboardKey}
 *
 * @package Clesson\Silverstripe\Dashboard
 * @subpackage Controllers
 */
class DashboardController extends Controller
{
    /**
     * Allowed actions for this controller.
     *
     * @config
     */
    private static array $allowed_actions = [
        'handleConfig',
        'handlePanels',
        'recentlyEditedPages',
        'recentlyUploadedFiles',
        'recentMembers',
    ];

    /**
     * URL handler mappings.
     *
     * @config
     */
    private static array $url_handlers = [
        'config' => 'handleConfig',
        'panels' => 'handlePanels',
        'recently-edited-pages' => 'recentlyEditedPages',
        'recently-uploaded-files' => 'recentlyUploadedFiles',
        'recent-members' => 'recentMembers',
    ];

    /**
     * Ensures only authenticated users can access this controller.
     *
     * @return void
     */
    protected function init(): void
    {
        parent::init();

        $member = Security::getCurrentUser();

        if (!$member) {
            $this->httpError(
                403,
                _t(__CLASS__ . '.NOT_LOGGED_IN', 'You must be logged in.')
            );
        }

        $locale = $member->Locale ?: i18n::get_locale();
        i18n::set_locale($locale);
    }

    /**
     * Handles GET and POST requests for dashboard configuration.
     *
     * GET  /dashboard-api/config?key={dashboardKey} — returns the user's panel config as JSON
     * POST /dashboard-api/config — saves the user's panel config
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleConfig(HTTPRequest $request): HTTPResponse
    {
        if ($request->isGET()) {
            return $this->loadConfig($request);
        }

        if ($request->isPOST()) {
            return $this->saveConfig($request);
        }

        return $this->jsonError(
            400,
            _t(__CLASS__ . '.INVALID_REQUEST', 'Invalid request.')
        );
    }

    /**
     * Returns all available panel classes as JSON.
     *
     * GET /dashboard-api/panels?key={dashboardKey}
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handlePanels(HTTPRequest $request): HTTPResponse
    {
        $panelClasses = ClassInfo::subclassesFor(DashboardPanel::class, false);
        $result = [];

        foreach ($panelClasses as $className) {
            /** @var DashboardPanel $panel */
            $panel = Injector::inst()->create($className);
            $result[] = $this->serializePanel($panel);
        }

        return $this->jsonResponse($result);
    }

    /**
     * Returns the 10 most recently edited pages as an HTML table.
     *
     * GET /dashboard-api/recently-edited-pages
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function recentlyEditedPages(HTTPRequest $request): HTTPResponse
    {
        $limit = (int) ($request->getVar('limit') ?: 10);
        $isDouble = $request->getVar('width') === 'double';
        $pages = $this->getRecentPages($limit, $isDouble);
        $html = $this->buildRecentPagesHtml($pages, $isDouble);

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($html);

        return $response;
    }

    /**
     * Queries the most recently edited SiteTree pages.
     *
     * @param int $limit
     * @param bool $isDouble
     * @return array
     */
    private function getRecentPages(int $limit = 10, bool $isDouble = false): array
    {
        if (!class_exists('SilverStripe\\CMS\\Model\\SiteTree')) {
            return [];
        }

        $siteTreeClass = 'SilverStripe\\CMS\\Model\\SiteTree';
        $pages = $siteTreeClass::get()
            ->sort('LastEdited', 'DESC')
            ->limit($limit);

        $result = [];
        foreach ($pages as $page) {
            $entry = [
                'title' => $page->Title ?: _t(__CLASS__ . '.UNTITLED', '(Untitled)'),
                'lastEdited' => DBDatetime::create_field('Datetime', $page->LastEdited)->Ago(),
                'editLink' => 'admin/pages/edit/show/' . $page->ID,
            ];

            if ($isDouble) {
                $entry['isPublished'] = $page->isPublished();
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Builds an HTML table from the recent pages data.
     *
     * @param array $pages
     * @return string
     */
    private function buildRecentPagesHtml(array $pages, bool $isDouble = false): string
    {
        if (empty($pages)) {
            return $this->buildTimestamp()
                . '<p style="color:#6c757d;font-style:italic;">'
                . _t(__CLASS__ . '.NO_PAGES', 'No pages found.')
                . '</p>';
        }

        $html = $this->buildTimestamp();
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tbody>';

        foreach ($pages as $page) {
            $html .= '<tr style="border-bottom:1px solid #eee;">';
            $html .= '<td style="padding:6px 8px;">';
            $html .= '<a href="' . htmlspecialchars($page['editLink']) . '" '
                . 'style="color:#0071c4;text-decoration:none;">';
            $html .= htmlspecialchars($page['title']);
            $html .= '</a>';
            $html .= '</td>';

            if ($isDouble && isset($page['isPublished'])) {
                $html .= '<td style="padding:6px 8px;text-align:center;white-space:nowrap;">';
                $html .= $this->buildStatusBadge($page['isPublished']);
                $html .= '</td>';
            }

            $html .= '<td style="padding:6px 8px;color:#6c757d;text-align:right;white-space:nowrap;">';
            $html .= htmlspecialchars($page['lastEdited']);
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Builds a small status badge for published/draft state.
     *
     * @param bool $isPublished
     * @return string
     */
    private function buildStatusBadge(bool $isPublished): string
    {
        if ($isPublished) {
            $label = _t(__CLASS__ . '.PUBLISHED', 'Published');
            return '<span style="display:inline-block;padding:2px 8px;font-size:11px;border-radius:3px;'
                . 'background:#d4edda;color:#155724;">' . htmlspecialchars($label) . '</span>';
        }

        $label = _t(__CLASS__ . '.DRAFT', 'Draft');
        return '<span style="display:inline-block;padding:2px 8px;font-size:11px;border-radius:3px;'
            . 'background:#fff3cd;color:#856404;">' . htmlspecialchars($label) . '</span>';
    }

    /**
     * Returns the 5 most recently uploaded files as an HTML table.
     *
     * GET /dashboard-api/recently-uploaded-files
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function recentlyUploadedFiles(HTTPRequest $request): HTTPResponse
    {
        $limit = (int) ($request->getVar('limit') ?: 5);
        $isDouble = $request->getVar('width') === 'double';
        $files = $this->getRecentFiles($limit, $isDouble);
        $html = $this->buildRecentFilesHtml($files, $isDouble);

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($html);

        return $response;
    }

    /**
     * Queries the most recently created File records (excluding folders).
     *
     * @param int $limit
     * @param bool $isDouble
     * @return array
     */
    private function getRecentFiles(int $limit = 5, bool $isDouble = false): array
    {
        if (!class_exists('SilverStripe\\Assets\\File')) {
            return [];
        }

        $fileClass = 'SilverStripe\\Assets\\File';
        $folderClass = 'SilverStripe\\Assets\\Folder';
        $files = $fileClass::get()
            ->exclude('ClassName', $folderClass)
            ->sort('Created', 'DESC')
            ->limit($limit);

        $result = [];
        foreach ($files as $file) {
            $entry = [
                'name' => $file->Name ?: _t(__CLASS__ . '.UNTITLED', '(Untitled)'),
                'created' => DBDatetime::create_field('Datetime', $file->Created)->Ago(),
                'editLink' => 'admin/assets/EditForm/field/File/item/' . $file->ID . '/edit',
            ];

            if ($isDouble) {
                $entry['size'] = $this->formatFileSize($file->getAbsoluteSize());
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Builds an HTML table from the recent files data.
     *
     * @param array $files
     * @return string
     */
    private function buildRecentFilesHtml(array $files, bool $isDouble = false): string
    {
        if (empty($files)) {
            return $this->buildTimestamp()
                . '<p style="color:#6c757d;font-style:italic;">'
                . _t(__CLASS__ . '.NO_FILES', 'No files found.')
                . '</p>';
        }

        $html = $this->buildTimestamp();
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tbody>';

        foreach ($files as $file) {
            $html .= '<tr style="border-bottom:1px solid #eee;">';
            $html .= '<td style="padding:6px 8px;">';
            $html .= '<a href="' . htmlspecialchars($file['editLink']) . '" '
                . 'style="color:#0071c4;text-decoration:none;">';
            $html .= htmlspecialchars($file['name']);
            $html .= '</a>';
            $html .= '</td>';

            if ($isDouble && isset($file['size'])) {
                $html .= '<td style="padding:6px 8px;color:#6c757d;text-align:right;white-space:nowrap;">';
                $html .= htmlspecialchars($file['size']);
                $html .= '</td>';
            }

            $html .= '<td style="padding:6px 8px;color:#6c757d;text-align:right;white-space:nowrap;">';
            $html .= htmlspecialchars($file['created']);
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Formats a file size in bytes to a human-readable string.
     *
     * @param int $bytes
     * @return string
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Returns the 5 most recently added members as an HTML table.
     *
     * GET /dashboard-api/recent-members
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function recentMembers(HTTPRequest $request): HTTPResponse
    {
        $limit = (int) ($request->getVar('limit') ?: 5);
        $members = $this->getRecentMembers($limit);
        $html = $this->buildRecentMembersHtml($members);

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($html);

        return $response;
    }

    /**
     * Queries the most recently created Member records.
     *
     * @param int $limit
     * @return array
     */
    private function getRecentMembers(int $limit = 5): array
    {
        if (!class_exists('SilverStripe\\Security\\Member')) {
            return [];
        }

        $memberClass = 'SilverStripe\\Security\\Member';
        $members = $memberClass::get()
            ->sort('Created', 'DESC')
            ->limit($limit);

        $result = [];
        foreach ($members as $member) {
            $name = trim($member->FirstName . ' ' . $member->Surname);

            $result[] = [
                'name' => $name ?: ($member->Email ?: _t(__CLASS__ . '.UNTITLED', '(Untitled)')),
                'lastLogin' => $member->LastEdited
                    ? DBDatetime::create_field('Datetime', $member->LastEdited)->Ago()
                    : _t(__CLASS__ . '.NEVER', 'Never'),
                'created' => DBDatetime::create_field('Datetime', $member->Created)->Ago(),
                'editLink' => 'admin/security/EditForm/field/Members/item/' . $member->ID . '/edit',
            ];
        }

        return $result;
    }

    /**
     * Builds an HTML table from the recent members data.
     *
     * @param array $members
     * @return string
     */
    private function buildRecentMembersHtml(array $members): string
    {
        if (empty($members)) {
            return $this->buildTimestamp()
                . '<p style="color:#6c757d;font-style:italic;">'
                . _t(__CLASS__ . '.NO_MEMBERS', 'No members found.')
                . '</p>';
        }

        $labelLastLogin = _t(__CLASS__ . '.LAST_LOGIN', 'Last login');
        $labelCreated = _t(__CLASS__ . '.LABEL_CREATED', 'Created');

        $html = $this->buildTimestamp();
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<thead><tr style="border-bottom:2px solid #dee2e6;">';
        $html .= '<th style="padding:6px 8px;text-align:left;font-weight:600;"></th>';
        $html .= '<th style="padding:6px 8px;text-align:right;font-weight:600;color:#6c757d;font-size:12px;">' . htmlspecialchars($labelLastLogin) . '</th>';
        $html .= '<th style="padding:6px 8px;text-align:right;font-weight:600;color:#6c757d;font-size:12px;">' . htmlspecialchars($labelCreated) . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($members as $member) {
            $html .= '<tr style="border-bottom:1px solid #eee;">';
            $html .= '<td style="padding:6px 8px;">';
            $html .= '<a href="' . htmlspecialchars($member['editLink']) . '" '
                . 'style="color:#0071c4;text-decoration:none;">';
            $html .= htmlspecialchars($member['name']);
            $html .= '</a>';
            $html .= '</td>';
            $html .= '<td style="padding:6px 8px;color:#6c757d;text-align:right;white-space:nowrap;">';
            $html .= htmlspecialchars($member['lastLogin']);
            $html .= '</td>';
            $html .= '<td style="padding:6px 8px;color:#6c757d;text-align:right;white-space:nowrap;">';
            $html .= htmlspecialchars($member['created']);
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Loads the dashboard configuration for the current user.
     *
     * If no configuration exists yet, creates the default layout.
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    private function loadConfig(HTTPRequest $request): HTTPResponse
    {
        $dashboardKey = (string) $request->getVar('key');
        $member = Security::getCurrentUser();
        $configs = $this->getConfigsForMember($member->ID, $dashboardKey);

        if ($configs->count() === 0) {
            $this->createDefaultConfig($member->ID, $dashboardKey);
            $configs = $this->getConfigsForMember($member->ID, $dashboardKey);
        }

        return $this->jsonResponse($this->serializeConfigs($configs));
    }

    /**
     * Saves the dashboard configuration for the current user.
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    private function saveConfig(HTTPRequest $request): HTTPResponse
    {
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->jsonError(403, _t(__CLASS__ . '.INVALID_REQUEST', 'Invalid request.'));
        }

        $body = json_decode($request->getBody(), true);

        if (!is_array($body)) {
            return $this->jsonError(400, _t(__CLASS__ . '.INVALID_REQUEST', 'Invalid request.'));
        }

        $member = Security::getCurrentUser();
        $dashboardKey = $body['key'] ?? '';

        $this->deleteExistingConfigs($member->ID, $dashboardKey);
        $this->writeNewConfigs($member->ID, $dashboardKey, $body['panels'] ?? []);

        return $this->jsonResponse([
            'success' => true,
            'message' => _t(__CLASS__ . '.CONFIG_SAVED', 'Dashboard configuration saved.'),
        ]);
    }

    /**
     * Deletes existing configuration records for a member and dashboard key.
     *
     * @param int $memberID
     * @param string $dashboardKey
     * @return void
     */
    private function deleteExistingConfigs(int $memberID, string $dashboardKey): void
    {
        $existing = $this->getConfigsForMember($memberID, $dashboardKey);

        foreach ($existing as $record) {
            $record->delete();
        }
    }

    /**
     * Writes new configuration records from the submitted panel data.
     *
     * @param int $memberID
     * @param string $dashboardKey
     * @param array $panels
     * @return void
     */
    private function writeNewConfigs(int $memberID, string $dashboardKey, array $panels): void
    {
        foreach ($panels as $index => $panelData) {
            $config = DashboardUserConfig::create();
            $config->DashboardKey = $dashboardKey;
            $config->MemberID = $memberID;
            $config->PanelClass = $panelData['panelClass'] ?? '';
            $config->Sort = $index;
            $config->Width = $panelData['width'] ?? 'single';
            $config->RefreshInterval = (int) ($panelData['refreshInterval'] ?? 0);
            $config->Visible = (bool) ($panelData['visible'] ?? true);
            $config->Settings = json_encode($panelData['settings'] ?? [], JSON_UNESCAPED_UNICODE);
            $config->write();
        }
    }

    /**
     * Returns the configuration records for a member and dashboard key.
     *
     * @param int $memberID
     * @param string $dashboardKey
     * @return DataList
     */
    private function getConfigsForMember(int $memberID, string $dashboardKey): DataList
    {
        return DashboardUserConfig::get()->filter([
            'MemberID' => $memberID,
            'DashboardKey' => $dashboardKey,
        ]);
    }

    /**
     * Creates the default panel configuration for a new user.
     *
     * Uses the default panels registered for the given dashboard key.
     *
     * @param int $memberID
     * @param string $dashboardKey
     * @return void
     */
    private function createDefaultConfig(int $memberID, string $dashboardKey): void
    {
        $panelClasses = ClassInfo::subclassesFor(DashboardPanel::class, false);
        $sort = 0;

        foreach ($panelClasses as $className) {
            /** @var DashboardPanel $panel */
            $panel = Injector::inst()->create($className);
            $config = DashboardUserConfig::create();
            $config->DashboardKey = $dashboardKey;
            $config->MemberID = $memberID;
            $config->PanelClass = $panel->getIdentifier();
            $config->Sort = $sort;
            $config->Width = $panel->getDefaultWidth();
            $config->RefreshInterval = $panel->getDefaultRefreshInterval();
            $config->Visible = true;
            $config->Settings = json_encode($panel->getDefaultSettings(), JSON_UNESCAPED_UNICODE);
            $config->write();
            $sort++;
        }
    }

    /**
     * Serializes a DashboardPanel instance to an array.
     *
     * @param DashboardPanel $panel
     * @return array
     */
    private function serializePanel(DashboardPanel $panel): array
    {
        return [
            'identifier' => $panel->getIdentifier(),
            'title' => $panel->getTitle(),
            'resourceURL' => $panel->getResourceURL(),
            'css' => $panel->getCSS(),
            'javaScript' => $panel->getJavaScript(),
            'defaultWidth' => $panel->getDefaultWidth(),
            'defaultRefreshInterval' => $panel->getDefaultRefreshInterval(),
            'hasSettings' => $panel->hasSettings(),
            'defaultSettings' => $panel->getDefaultSettings(),
            'settingsFields' => $this->serializeSettingsFields($panel),
        ];
    }

    /**
     * Serializes the settings fields of a panel for the JS frontend.
     *
     * @param DashboardPanel $panel
     * @return array
     */
    private function serializeSettingsFields(DashboardPanel $panel): array
    {
        if (!$panel->hasSettings()) {
            return [];
        }

        $fields = [];
        foreach ($panel->getSettingsFields() as $field) {
            $entry = [
                'name' => $field->getName(),
                'label' => $field->Title() ?: $field->getName(),
                'type' => $this->mapFieldType($field),
                'value' => $field->dataValue(),
            ];

            if ($entry['type'] === 'select' && method_exists($field, 'getSource')) {
                $entry['options'] = $field->getSource();
            }

            $fields[] = $entry;
        }

        return $fields;
    }

    /**
     * Maps a Silverstripe form field to a simple HTML input type.
     *
     * @param mixed $field
     * @return string
     */
    private function mapFieldType(mixed $field): string
    {
        $className = get_class($field);

        return match (true) {
            str_contains($className, 'NumericField') => 'number',
            str_contains($className, 'CheckboxField') => 'checkbox',
            str_contains($className, 'DropdownField') => 'select',
            default => 'text',
        };
    }

    /**
     * Serializes a list of DashboardUserConfig records to an array.
     *
     * @param DataList $configs
     * @return array
     */
    private function serializeConfigs(DataList $configs): array
    {
        $result = [];

        foreach ($configs as $config) {
            $result[] = [
                'panelClass' => $config->PanelClass,
                'sort' => (int) $config->Sort,
                'width' => $config->Width,
                'refreshInterval' => (int) $config->RefreshInterval,
                'visible' => (bool) $config->Visible,
                'settings' => json_decode($config->Settings ?: '{}', true) ?: [],
            ];
        }

        return $result;
    }

    /**
     * Builds a timestamp line showing when the panel content was last refreshed.
     *
     * Uses the panel border color (#dee2e6) for consistency.
     *
     * @return string
     */
    private function buildTimestamp(): string
    {
        $now = DBDatetime::now()->Format('dd.MM.yyyy HH:mm:ss');

        return '<div style="display:inline-block;font-size:11px;color:#fff;background:#adb5bd;'
            . 'padding:2px 8px;border-radius:4px;margin-bottom:10px;">'
            . htmlspecialchars($now)
            . '</div>';
    }

    /**
     * Returns a JSON-encoded HTTPResponse.
     *
     * @param array $data
     * @param int $statusCode
     * @return HTTPResponse
     */
    private function jsonResponse(array $data, int $statusCode = 200): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->setStatusCode($statusCode);
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response;
    }

    /**
     * Returns a JSON error response.
     *
     * @param int $statusCode
     * @param string $message
     * @return HTTPResponse
     */
    private function jsonError(int $statusCode, string $message): HTTPResponse
    {
        return $this->jsonResponse(['error' => $message], $statusCode);
    }
}







