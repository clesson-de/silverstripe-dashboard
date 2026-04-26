import { useState, useEffect, useCallback } from 'react';

/**
 * Custom hook to load and save dashboard configuration via the API.
 *
 * @param {string} configUrl - URL to GET the dashboard config
 * @param {string} saveUrl - URL to POST the dashboard config
 * @param {string} panelsUrl - URL to GET available panels
 * @param {string} dashboardKey - The dashboard key identifier
 */
export function useDashboardConfig(configUrl, saveUrl, panelsUrl, dashboardKey) {
  const [config, setConfig] = useState(null);
  const [availablePanels, setAvailablePanels] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const loadConfig = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(configUrl, { credentials: 'same-origin' });

      if (!response.ok) {
        throw new Error('Failed to load dashboard configuration');
      }

      const data = await response.json();
      setConfig(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [configUrl]);

  const loadPanels = useCallback(async () => {
    try {
      const response = await fetch(panelsUrl, { credentials: 'same-origin' });

      if (!response.ok) {
        throw new Error('Failed to load available panels');
      }

      const data = await response.json();
      setAvailablePanels(data);
    } catch (err) {
      console.error('Failed to load panels:', err);
    }
  }, [panelsUrl]);

  const saveConfig = useCallback(async (panels) => {
    try {
      const tokenEl = document.querySelector('input[name="SecurityID"]');
      const token = tokenEl ? tokenEl.value : '';

      const response = await fetch(saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-SecurityID': token,
        },
        body: JSON.stringify({
          key: dashboardKey,
          panels: panels,
          SecurityID: token,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to save dashboard configuration');
      }

      setConfig(panels);
    } catch (err) {
      console.error('Failed to save config:', err);
    }
  }, [saveUrl, dashboardKey]);

  useEffect(() => {
    loadConfig();
    loadPanels();
  }, [loadConfig, loadPanels]);

  return {
    config,
    availablePanels,
    loading,
    error,
    saveConfig,
    reloadConfig: loadConfig,
  };
}

