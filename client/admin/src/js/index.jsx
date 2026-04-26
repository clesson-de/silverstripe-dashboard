import React from 'react';
import { createRoot } from 'react-dom/client';
import Dashboard from './components/Dashboard';

/**
 * Entwine hook that mounts the React Dashboard component
 * when a .dashboard-field element appears in the DOM (AJAX-safe).
 * Unmounts cleanly when the element is removed.
 */
jQuery.entwine('ss', ($) => {
  $('.dashboard-field').entwine({
    ReactRoot: null,

    onmatch() {
      const el = this[0];
      const configUrl = el.getAttribute('data-config-url');
      const panelsUrl = el.getAttribute('data-panels-url');
      const saveUrl = el.getAttribute('data-save-url');
      const dashboardKey = el.getAttribute('data-dashboard-key');

      const root = createRoot(el);
      root.render(
        React.createElement(Dashboard, {
          configUrl,
          panelsUrl,
          saveUrl,
          dashboardKey,
        })
      );

      this.setReactRoot(root);
    },

    onunmatch() {
      const root = this.getReactRoot();

      if (root) {
        root.unmount();
        this.setReactRoot(null);
      }
    },
  });
});

