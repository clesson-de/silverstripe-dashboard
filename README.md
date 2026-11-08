# Silverstripe Dashboard

A configurable dashboard field for SilverStripe 6. It renders a full-width, React-powered panel grid inside any CMS form. Users can rearrange panels via drag & drop, toggle visibility, and configure auto-refresh intervals. Panel state is persisted per user.

## Features

- **DashboardField** — a full-width FormField that renders a React dashboard inside the CMS
- **Pluggable panels** — create custom panels by extending the abstract `DashboardPanel` class
- **Per-user configuration** — panel order, width, visibility, and refresh interval are stored per user
- **Drag & drop** — reorder panels with native HTML5 drag and drop
- **Panel selector** — modal dialog to show/hide panels
- **Auto-refresh** — panels can auto-refresh their content at configurable intervals
- **API-driven** — all configuration is managed via a JSON API

## Requirements

- Silverstripe Framework `^6`
- Silverstripe Admin `^3`
- PHP 8.3+

## Installation

```bash
composer require clesson-de/silverstripe-dashboard
composer vendor-expose
```

Then run:

```
/dev/build?flush=all
```

## Usage

### Adding a Dashboard to a CMS Form

Use `DashboardField` in any `getCMSFields()` method:

```php
use Clesson\Silverstripe\Dashboard\Forms\DashboardField;

public function getCMSFields(): FieldList
{
    $fields = parent::getCMSFields();

    /** @var DashboardField $dashboardField */
    $dashboardField = DashboardField::create('Dashboard', 'my-dashboard-key');

    $fields->addFieldToTab('Root.Dashboard', $dashboardField);

    return $fields;
}
```

### Creating a Custom Panel

Create a class that extends `DashboardPanel`:

```php
<?php

declare(strict_types=1);

namespace App\Panels;

use Clesson\Silverstripe\Dashboard\Panels\DashboardPanel;
use SilverStripe\Control\Director;

class RecentOrdersPanel extends DashboardPanel
{
    public function getTitle(): string
    {
        return _t(__CLASS__ . '.TITLE', 'Recent Orders');
    }

    public function getResourceURL(): string
    {
        return Director::absoluteURL('api/recent-orders');
    }

    public function getDefaultWidth(): string
    {
        return 'double';
    }

    public function getDefaultRefreshInterval(): int
    {
        return 60; // Refresh every 60 seconds
    }
}
```

The `getResourceURL()` must return a URL that serves HTML content. The dashboard loads this via `fetch()` and renders it inside the panel.

## Configuration Options

### Standalone Dashboard Admin (Landing Page)

The module includes a standalone `DashboardAdmin` that can serve as the CMS landing page. It is **disabled by default** and must be explicitly enabled via YAML configuration:

```yaml
# app/_config/dashboard.yml
---
Name: app-dashboard
---
Clesson\Silverstripe\Dashboard\Admins\DashboardAdmin:
  enabled: true
```

To make it the **default landing page** after login, you must override `AdminRootController.default_panel`. This requires a separate config block with `After: cmsdefaultadmin` — otherwise `silverstripe/cms` may override your setting:

```yaml
# app/_config/dashboard.yml (continued)
---
Name: app-dashboard-default
After:
  - 'cmsdefaultadmin'
---
SilverStripe\Admin\AdminRootController:
  default_panel: 'Clesson\Silverstripe\Dashboard\Admins\DashboardAdmin'
```

> **Important:** The `After: cmsdefaultadmin` ensures that your config is applied **after** the `silverstripe/cms` module sets `CMSMain` as the default panel. Without it, the load order is undefined and users may still land on the "Pages" section after login.

You can also restrict which panels are available and set defaults:

```yaml
Clesson\Silverstripe\Dashboard\Admins\DashboardAdmin:
  enabled: true
  dashboard_key: 'main'
  allowed_panels:
    - App\Panels\RecentOrdersPanel
    - App\Panels\SalesChartPanel
  default_panels:
    - App\Panels\RecentOrdersPanel
```

| Config key | Type | Default | Description |
|---|---|---|---|
| `enabled` | `bool` | `false` | Show the admin in the CMS menu |
| `dashboard_key` | `string` | `'main'` | Key for per-user panel storage |
| `allowed_panels` | `string[]` | `[]` (all) | Restrict available panel types |
| `default_panels` | `string[]` | `[]` | Panels created for new users |

### Panel Width

Panels support two widths:

- `'single'` — occupies one grid column (default)
- `'double'` — spans two grid columns

### Auto-Refresh

Set `getDefaultRefreshInterval()` to a value in seconds. `0` disables auto-refresh (default).

### Panel Assets

Panels can optionally provide custom CSS and JavaScript files:

```php
public function getCSS(): ?string
{
    return 'my-module: client/admin/dist/panel.css';
}

public function getJavaScript(): ?string
{
    return 'my-module: client/admin/dist/panel.js';
}
```

## API Endpoints

All endpoints require an authenticated CMS user.

| Method | URL | Description |
|---|---|---|
| `GET` | `/dashboard-api/config?key={dashboardKey}` | Returns the panel config for the current user |
| `POST` | `/dashboard-api/config` | Saves the panel config (requires SecurityID token) |
| `GET` | `/dashboard-api/panels?key={dashboardKey}` | Lists all available panel classes |

## Frontend Assets

The module ships with compiled admin CSS and JS in `client/admin/dist/`.
If you want to modify the styles or scripts, you need to recompile them.

### Prerequisites

The required Node.js version is defined in `.nvmrc`. Switch to it with:

```bash
nvm use
```

### Install dependencies

```bash
npm install
```

### Build

```bash
npm run build
```

This compiles:

- `client/admin/src/js/index.jsx` → `client/admin/dist/bundle.js`
- `client/admin/src/scss/dashboard.scss` → `client/admin/dist/bundle.css`

### Watch mode (during development)

```bash
npm run watch
```

### Output files

The compiled files in `client/admin/dist/` are exposed via `composer vendor-expose`
and referenced in PHP via `Requirements::css()` / `Requirements::javascript()`.
Their filenames are **static** — they must not be renamed.

## Developer Documentation

### Architecture

The dashboard is split into a PHP backend and a React frontend:

- **PHP** provides the `DashboardField` (FormField), the `DashboardPanel` abstract class, the `DashboardUserConfig` model (per-user state), and the `DashboardController` (JSON API).
- **React** renders the panel grid, handles drag & drop, and communicates with the API via `fetch()`.

### Creating Panels

1. Create a class extending `Clesson\Silverstripe\Dashboard\Panels\DashboardPanel`
2. Implement `getTitle()` — return a translated panel title
3. Implement `getResourceURL()` — return a URL that serves HTML content
4. Optionally override `getDefaultWidth()`, `getDefaultRefreshInterval()`, `getCSS()`, `getJavaScript()`
5. Run `/dev/build?flush=all` — the panel is automatically discovered

### Panel Content Endpoint

The URL returned by `getResourceURL()` must serve content that can be rendered as HTML. The dashboard fetches this URL and injects the response via `innerHTML`. You can return:

- Plain HTML
- JSON (you would need custom JS to render it)
- Any text content

### User Configuration Model

`DashboardUserConfig` stores:

| Field | Type | Description |
|---|---|---|
| `DashboardKey` | `Varchar(100)` | Identifies which dashboard |
| `PanelClass` | `Varchar(255)` | FQCN of the panel class |
| `Sort` | `Int` | Display order |
| `Width` | `Varchar(10)` | `'single'` or `'double'` |
| `RefreshInterval` | `Int` | Auto-refresh in seconds |
| `Visible` | `Boolean` | Whether the panel is shown |
| `MemberID` | `Int` | The owning user |

## License

BSD-3-Clause. See [LICENSE](LICENSE).
