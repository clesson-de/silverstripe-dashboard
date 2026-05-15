/* Dashboard bundle JS — compiled via: npm run build:js */

/**
 * Dashboard field — jQuery Entwine implementation.
 *
 * Each panel header shows a width toggle and a settings cog icon.
 * Clicking the cog replaces the panel content with settings fields.
 * Saving settings restores the original content with updated data.
 * The "Manage panels" dialog only adds/removes panels.
 */
(function ($) {
  'use strict';

  $.entwine('ss', function ($) {

    $('.dashboard-field').entwine({

      onmatch: function () {
        this._super();
        this._loadDashboard();
      },

      onunmatch: function () {
        this._clearRefreshTimers();
        this._super();
      },

      _label: function (key, fallback) {
        return this.data('label-' + key) || fallback;
      },

      _loadDashboard: function () {
        var self = this;
        var configURL = this.data('config-url');
        var panelsURL = this.data('panels-url');

        Promise.all([
          fetch(configURL, { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
          fetch(panelsURL, { credentials: 'same-origin' }).then(function (r) { return r.json(); })
        ]).then(function (results) {
          self.data('panelConfigs', results[0]);
          self.data('availablePanels', results[1]);
          self._render();
        }).catch(function (err) {
          console.error('[Dashboard] Failed to load:', err);
          self.html('<div class="dashboard__error">Failed to load dashboard.</div>');
        });
      },

      _render: function () {
        var configs = this.data('panelConfigs') || [];
        var panels = this.data('availablePanels') || [];
        var labelManage = this._label('manage-panels', 'Manage panels');
        var labelLoading = this._label('loading', 'Loading…');
        var labelSettings = this._label('settings', 'Settings');
        var labelSingle = this._label('single', 'Single');
        var labelDouble = this._label('double', 'Double');
        var html = '';

        html += '<div class="dashboard">';
        html += '  <div class="dashboard__toolbar">';
        html += '    <button type="button" class="dashboard__toolbar-btn js-manage-panels">';
        html += '      <span class="font-icon-plus-circled"></span> ' + this._escHtml(labelManage);
        html += '    </button>';
        html += '  </div>';
        html += '  <div class="dashboard__grid js-dashboard-grid">';

        for (var i = 0; i < configs.length; i++) {
          var cfg = configs[i];
          if (!cfg.visible) continue;

          var panelInfo = this._findPanelInfo(cfg.panelClass, panels);
          if (!panelInfo) continue;
          var title = panelInfo.title;
          var isDouble = cfg.width === 'double';
          var widthClass = isDouble ? ' dashboard-panel--double' : '';
          var resourceURL = this._buildResourceURL(panelInfo, cfg.settings || {}, cfg.width || 'single');

          html += '<div class="dashboard-panel' + widthClass + '" ';
          html += '     data-panel-class="' + this._escAttr(cfg.panelClass) + '" ';
          html += '     data-resource-url="' + this._escAttr(resourceURL) + '" ';
          html += '     data-refresh="' + (cfg.refreshInterval || 0) + '" ';
          html += '     draggable="true">';

          // Header with title, width toggle and settings cog
          html += '  <div class="dashboard-panel__header">';
          html += '    <span class="dashboard-panel__title">' + this._escHtml(title) + '</span>';
          html += '    <span class="dashboard-panel__header-controls">';
          html += '      <label class="dashboard-panel__width-toggle" title="' + this._escAttr(isDouble ? labelDouble : labelSingle) + '">';
          html += '        <input type="checkbox" class="js-width-toggle" ' + (isDouble ? 'checked' : '') + '>';
          html += '        <span class="dashboard-panel__width-switch"></span>';
          html += '      </label>';
          html += '      <button type="button" class="dashboard-panel__settings-btn js-open-settings" ';
          html += '              data-panel-class="' + this._escAttr(cfg.panelClass) + '" ';
          html += '              title="' + this._escAttr(labelSettings) + '">';
          html += '        <span class="font-icon-cog"></span>';
          html += '      </button>';
          html += '    </span>';
          html += '  </div>';

          // Content area
          html += '  <div class="dashboard-panel__content js-panel-content">';
          html += '    <div class="dashboard-panel__loading">' + this._escHtml(labelLoading) + '</div>';
          html += '  </div>';

          html += '</div>';
        }

        html += '  </div>';
        html += '</div>';

        this.html(html);
        this._loadPanelContents();
        this._setupDragDrop();
        this._setupRefreshTimers();
      },

      _buildResourceURL: function (panelInfo, settings, width) {
        if (!panelInfo || !panelInfo.resourceURL) return '';
        var url = panelInfo.resourceURL;
        var params = [];
        var keys = Object.keys(settings || {});
        for (var i = 0; i < keys.length; i++) {
          if (keys[i] === 'refreshInterval') continue; // not a query param
          params.push(encodeURIComponent(keys[i]) + '=' + encodeURIComponent(settings[keys[i]]));
        }
        if (width) {
          params.push('width=' + encodeURIComponent(width));
        }
        if (params.length > 0) {
          url += (url.indexOf('?') === -1 ? '?' : '&') + params.join('&');
        }
        return url;
      },

      _findPanelInfo: function (panelClass, panels) {
        for (var i = 0; i < panels.length; i++) {
          if (panels[i].identifier === panelClass) return panels[i];
        }
        return null;
      },

      _findConfig: function (panelClass) {
        var configs = this.data('panelConfigs') || [];
        for (var i = 0; i < configs.length; i++) {
          if (configs[i].panelClass === panelClass) return configs[i];
        }
        return null;
      },

      _updateConfig: function (panelClass, updates) {
        var configs = this.data('panelConfigs') || [];
        for (var i = 0; i < configs.length; i++) {
          if (configs[i].panelClass === panelClass) {
            for (var key in updates) {
              configs[i][key] = updates[key];
            }
            break;
          }
        }
        this.data('panelConfigs', configs);
      },

      _loadPanelContents: function () {
        this.find('.dashboard-panel').each(function () {
          var $panel = $(this);
          var url = $panel.data('resource-url');
          if (!url) {
            $panel.find('.js-panel-content').html('<div class="dashboard-panel__error">No resource URL</div>');
            return;
          }
          $panel._loadContent();
        });
      },

      _setupRefreshTimers: function () {
        var timers = [];
        this.find('.dashboard-panel').each(function () {
          var $panel = $(this);
          var $dashboard = $panel.closest('.dashboard-field');
          var panelClass = $panel.data('panel-class');
          var cfg = $dashboard._findConfig(panelClass);
          var interval = (cfg && cfg.settings && cfg.settings.refreshInterval)
            ? parseInt(cfg.settings.refreshInterval, 10)
            : 0;

          if (interval > 0) {
            var timer = setInterval(function () {
              // Only reload if not currently showing settings
              if (!$panel.data('showingSettings')) {
                $panel._loadContent();
              }
            }, interval * 60 * 1000);
            timers.push(timer);
          }
        });
        this.data('refreshTimers', timers);
      },

      _clearRefreshTimers: function () {
        var timers = this.data('refreshTimers') || [];
        for (var i = 0; i < timers.length; i++) {
          clearInterval(timers[i]);
        }
        this.data('refreshTimers', []);
      },

      _setupDragDrop: function () {
        var self = this;
        var grid = this.find('.js-dashboard-grid');
        var dragSrc = null;

        grid.on('dragstart', '.dashboard-panel', function (e) {
          dragSrc = this;
          $(this).css('opacity', '0.4');
          e.originalEvent.dataTransfer.effectAllowed = 'move';
          e.originalEvent.dataTransfer.setData('text/plain', '');
        });

        grid.on('dragover', '.dashboard-panel', function (e) {
          e.preventDefault();
          e.originalEvent.dataTransfer.dropEffect = 'move';
          $(this).addClass('dashboard-panel--drag-over');
        });

        grid.on('dragleave', '.dashboard-panel', function () {
          $(this).removeClass('dashboard-panel--drag-over');
        });

        grid.on('drop', '.dashboard-panel', function (e) {
          e.preventDefault();
          $(this).removeClass('dashboard-panel--drag-over');
          if (dragSrc !== this) {
            var $src = $(dragSrc);
            var $dst = $(this);
            if ($src.index() < $dst.index()) {
              $dst.after($src);
            } else {
              $dst.before($src);
            }
            self._saveConfig();
          }
        });

        grid.on('dragend', '.dashboard-panel', function () {
          $(this).css('opacity', '1');
          grid.find('.dashboard-panel').removeClass('dashboard-panel--drag-over');
        });
      },

      _saveConfig: function () {
        var dashboardKey = this.data('dashboard-key');
        var saveURL = this.data('save-url');
        var configs = this.data('panelConfigs') || [];
        var panels = [];

        this.find('.dashboard-panel').each(function (index) {
          var panelClass = $(this).data('panel-class');
          var existingCfg = null;
          for (var i = 0; i < configs.length; i++) {
            if (configs[i].panelClass === panelClass) {
              existingCfg = configs[i];
              break;
            }
          }
          panels.push({
            panelClass: panelClass,
            sort: index,
            width: existingCfg ? existingCfg.width : 'single',
            refreshInterval: existingCfg ? existingCfg.refreshInterval : 0,
            visible: true,
            settings: existingCfg ? (existingCfg.settings || {}) : {}
          });
        });

        for (var i = 0; i < configs.length; i++) {
          if (!configs[i].visible) {
            panels.push(configs[i]);
          }
        }

        var token = $('input[name="SecurityID"]').val() || '';
        fetch(saveURL, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-SecurityID': token },
          body: JSON.stringify({ key: dashboardKey, panels: panels, SecurityID: token })
        }).then(function (r) { return r.json(); }).catch(function (err) {
          console.error('[Dashboard] Save failed:', err);
        });
      },

      _escHtml: function (str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
      },

      _escAttr: function (str) {
        return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      }
    });

    /**
     * Individual dashboard panel.
     */
    $('.dashboard-panel').entwine({
      _loadContent: function () {
        var $panel = this;
        var url = this.data('resource-url');
        if (!url) return;

        var $content = $panel.find('.js-panel-content');
        var labelLoading = $panel.closest('.dashboard-field').data('label-loading') || 'Loading…';
        $content.html('<div class="dashboard-panel__loading">' + labelLoading + '</div>');

        fetch(url, { credentials: 'same-origin' })
          .then(function (r) {
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) {
              return r.json().then(function (data) {
                $content.html('<pre>' + JSON.stringify(data, null, 2) + '</pre>');
              });
            }
            return r.text().then(function (html) {
              $content.html(html);
            });
          })
          .catch(function (err) {
            $content.html('<div class="dashboard-panel__error">Failed to load panel content.</div>');
            console.error('[Dashboard] Panel load error:', err);
          });
      }
    });

    /**
     * Width toggle switch in panel header.
     */
    $('.js-width-toggle').entwine({
      onchange: function () {
        var $panel = this.closest('.dashboard-panel');
        var $dashboard = $panel.closest('.dashboard-field');
        var panelClass = $panel.data('panel-class');
        var isDouble = this.is(':checked');
        var newWidth = isDouble ? 'double' : 'single';
        var panels = $dashboard.data('availablePanels') || [];
        var panelInfo = $dashboard._findPanelInfo(panelClass, panels);
        var cfg = $dashboard._findConfig(panelClass);
        var settings = (cfg && cfg.settings) || {};

        $panel.toggleClass('dashboard-panel--double', isDouble);
        $dashboard._updateConfig(panelClass, { width: newWidth });

        // Rebuild resource URL with new width and reload content
        var resourceURL = $dashboard._buildResourceURL(panelInfo, settings, newWidth);
        $panel.data('resource-url', resourceURL);
        $panel._loadContent();

        $dashboard._saveConfig();
      }
    });

    /**
     * Settings cog in panel header — replaces content with settings form.
     */
    $('.js-open-settings').entwine({
      onclick: function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $panel = this.closest('.dashboard-panel');
        var $dashboard = $panel.closest('.dashboard-field');
        var $content = $panel.find('.js-panel-content');
        var panelClass = $panel.data('panel-class');
        var panels = $dashboard.data('availablePanels') || [];
        var panelInfo = $dashboard._findPanelInfo(panelClass, panels);
        var cfg = $dashboard._findConfig(panelClass);
        var currentSettings = (cfg && cfg.settings) || {};
        var labelSave = $dashboard._label('save', 'Save');
        var labelCancel = $dashboard._label('close', 'Cancel');

        if (!panelInfo || !panelInfo.settingsFields) return;

        // Remember the current content HTML so we can restore on cancel
        $panel.data('savedContent', $content.html());
        $panel.data('showingSettings', true);

        // Highlight the active cog
        this.addClass('dashboard-panel__settings-btn--active');

        var html = '<div class="dashboard-panel__settings-form">';

        for (var k = 0; k < panelInfo.settingsFields.length; k++) {
          var field = panelInfo.settingsFields[k];
          var fieldValue = (currentSettings[field.name] !== undefined) ? currentSettings[field.name] : field.value;

          html += '<div class="dashboard-panel__settings-field">';
          html += '  <label class="dashboard-panel__settings-label">' + $dashboard._escHtml(field.label) + '</label>';

          if (field.type === 'checkbox') {
            html += '  <input type="checkbox" class="js-setting-input" data-setting-name="' + field.name + '" ' + (fieldValue ? 'checked' : '') + '>';
          } else if (field.type === 'select' && field.options) {
            html += '  <select class="js-setting-input" data-setting-name="' + field.name + '">';
            var optKeys = Object.keys(field.options);
            for (var o = 0; o < optKeys.length; o++) {
              var optVal = optKeys[o];
              var optLabel = field.options[optVal];
              var selected = (String(fieldValue) === String(optVal)) ? ' selected' : '';
              html += '    <option value="' + $dashboard._escAttr(optVal) + '"' + selected + '>' + $dashboard._escHtml(optLabel) + '</option>';
            }
            html += '  </select>';
          } else {
            html += '  <input type="' + field.type + '" class="js-setting-input" data-setting-name="' + field.name + '" value="' + $dashboard._escAttr(String(fieldValue)) + '">';
          }

          html += '</div>';
        }

        html += '<div class="dashboard-panel__settings-actions">';
        html += '  <button type="button" class="dashboard-panel__settings-save js-save-settings" data-panel-class="' + $dashboard._escAttr(panelClass) + '">' + $dashboard._escHtml(labelSave) + '</button>';
        html += '  <button type="button" class="dashboard-panel__settings-cancel js-cancel-settings">' + $dashboard._escHtml(labelCancel) + '</button>';
        html += '</div>';
        html += '</div>';

        $content.html(html);
      }
    });

    /**
     * Save settings button — saves, restores content, reloads panel.
     */
    $('.js-save-settings').entwine({
      onclick: function (e) {
        e.preventDefault();
        var $panel = this.closest('.dashboard-panel');
        var $dashboard = $panel.closest('.dashboard-field');
        var panelClass = this.data('panel-class');
        var panels = $dashboard.data('availablePanels') || [];
        var panelInfo = $dashboard._findPanelInfo(panelClass, panels);

        // Collect settings
        var settings = {};
        $panel.find('.js-setting-input').each(function () {
          var name = $(this).data('setting-name');
          if ($(this).attr('type') === 'checkbox') {
            settings[name] = $(this).is(':checked');
          } else {
            settings[name] = $(this).val();
          }
        });

        // Update config
        $dashboard._updateConfig(panelClass, { settings: settings });
        $panel.data('showingSettings', false);
        $panel.find('.js-open-settings').removeClass('dashboard-panel__settings-btn--active');

        // Rebuild resource URL with new settings
        var cfgAfterSave = $dashboard._findConfig(panelClass);
        var currentWidth = (cfgAfterSave && cfgAfterSave.width) || 'single';
        var resourceURL = $dashboard._buildResourceURL(panelInfo, settings, currentWidth);
        $panel.data('resource-url', resourceURL);

        // Reload content
        $panel._loadContent();

        // Re-setup refresh timers with potentially new interval
        $dashboard._clearRefreshTimers();
        $dashboard._setupRefreshTimers();

        // Persist to server
        $dashboard._saveConfig();
      }
    });

    /**
     * Cancel settings — restores previous content without saving.
     */
    $('.js-cancel-settings').entwine({
      onclick: function (e) {
        e.preventDefault();
        var $panel = this.closest('.dashboard-panel');
        var $content = $panel.find('.js-panel-content');
        var savedContent = $panel.data('savedContent') || '';

        $panel.data('showingSettings', false);
        $panel.find('.js-open-settings').removeClass('dashboard-panel__settings-btn--active');
        $content.html(savedContent);
      }
    });

    /**
     * Manage panels button — only add/remove panels.
     */
    $('.js-manage-panels').entwine({
      onclick: function (e) {
        e.preventDefault();
        var $dashboard = this.closest('.dashboard-field');
        var configs = $dashboard.data('panelConfigs') || [];
        var panels = $dashboard.data('availablePanels') || [];
        var labelManage = $dashboard._label('manage-panels', 'Manage panels');
        var labelClose = $dashboard._label('close', 'Close');
        var labelSave = $dashboard._label('save', 'Save');
        var html = '';

        html += '<div class="panel-selector js-panel-selector">';
        html += '  <div class="panel-selector__dialog">';
        html += '    <h3 class="panel-selector__title">' + $dashboard._escHtml(labelManage) + '</h3>';
        html += '    <ul class="panel-selector__list">';

        for (var i = 0; i < panels.length; i++) {
          var panel = panels[i];
          var isVisible = false;

          for (var j = 0; j < configs.length; j++) {
            if (configs[j].panelClass === panel.identifier) {
              isVisible = configs[j].visible;
              break;
            }
          }

          html += '<li class="panel-selector__item">';
          html += '  <label class="panel-selector__label">';
          html += '    <input type="checkbox" class="js-panel-toggle" ';
          html += '           data-panel-class="' + panel.identifier + '" ';
          html += '           ' + (isVisible ? 'checked' : '') + '> ';
          html += '    ' + $dashboard._escHtml(panel.title);
          html += '  </label>';
          html += '</li>';
        }

        html += '    </ul>';
        html += '    <div class="panel-selector__footer">';
        html += '      <button type="button" class="panel-selector__save-btn js-save-selector">' + $dashboard._escHtml(labelSave) + '</button>';
        html += '      <button type="button" class="panel-selector__close-btn js-close-selector">' + $dashboard._escHtml(labelClose) + '</button>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        $('body').append(html);
      }
    });

    /**
     * Save button in panel selector.
     */
    $('.js-save-selector').entwine({
      onclick: function (e) {
        e.preventDefault();
        var $selector = this.closest('.js-panel-selector');
        var $dashboard = $('.dashboard-field');
        var configs = $dashboard.data('panelConfigs') || [];
        var panels = $dashboard.data('availablePanels') || [];

        $selector.find('.js-panel-toggle').each(function () {
          var panelClass = $(this).data('panel-class');
          var visible = $(this).is(':checked');
          var found = false;

          for (var i = 0; i < configs.length; i++) {
            if (configs[i].panelClass === panelClass) {
              configs[i].visible = visible;
              found = true;
              break;
            }
          }

          if (!found && visible) {
            var panelInfo = null;
            for (var k = 0; k < panels.length; k++) {
              if (panels[k].identifier === panelClass) { panelInfo = panels[k]; break; }
            }
            configs.push({
              panelClass: panelClass,
              sort: configs.length,
              width: panelInfo ? panelInfo.defaultWidth : 'single',
              refreshInterval: panelInfo ? panelInfo.defaultRefreshInterval : 0,
              visible: true,
              settings: panelInfo ? (panelInfo.defaultSettings || {}) : {}
            });
          }
        });

        $dashboard.data('panelConfigs', configs);
        $selector.remove();
        $dashboard._clearRefreshTimers();
        $dashboard._render();
        $dashboard._saveConfig();
      }
    });

    /**
     * Close button — closes without saving.
     */
    $('.js-close-selector').entwine({
      onclick: function (e) {
        e.preventDefault();
        this.closest('.js-panel-selector').remove();
      }
    });

  });
})(jQuery);
