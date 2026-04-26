import React, { useState, useCallback, useRef } from 'react';
import DashboardPanel from './DashboardPanel';
import PanelSelector from './PanelSelector';
import { useDashboardConfig } from '../hooks/useDashboardConfig';

/**
 * Main dashboard component that renders a grid of panels
 * with drag & drop reordering and a panel selector dialog.
 *
 * @param {Object} props
 * @param {string} props.configUrl - URL to GET the dashboard config
 * @param {string} props.saveUrl - URL to POST the dashboard config
 * @param {string} props.panelsUrl - URL to GET available panels
 * @param {string} props.dashboardKey - The dashboard key identifier
 */
export default function Dashboard({ configUrl, saveUrl, panelsUrl, dashboardKey }) {
  const {
    config,
    availablePanels,
    loading,
    error,
    saveConfig,
  } = useDashboardConfig(configUrl, saveUrl, panelsUrl, dashboardKey);

  const [selectorOpen, setSelectorOpen] = useState(false);
  const dragIndexRef = useRef(null);

  const getPanelInfo = useCallback((panelClass) => {
    return availablePanels.find((p) => p.identifier === panelClass) || null;
  }, [availablePanels]);

  const visiblePanels = config
    ? config.filter((p) => p.visible)
    : [];

  const handleDragStart = useCallback((e, index) => {
    dragIndexRef.current = index;
    e.dataTransfer.effectAllowed = 'move';
  }, []);

  const handleDrop = useCallback((e, dropIndex) => {
    e.preventDefault();
    const dragIndex = dragIndexRef.current;

    if (dragIndex === null || dragIndex === dropIndex || !config) {
      return;
    }

    const newConfig = [...config];
    const visibleItems = newConfig.filter((p) => p.visible);
    const dragItem = visibleItems[dragIndex];
    const dropItem = visibleItems[dropIndex];

    if (!dragItem || !dropItem) {
      return;
    }

    const dragSort = dragItem.sort;
    dragItem.sort = dropItem.sort;
    dropItem.sort = dragSort;

    newConfig.sort((a, b) => a.sort - b.sort);
    newConfig.forEach((item, i) => { item.sort = i; });

    saveConfig(newConfig);
    dragIndexRef.current = null;
  }, [config, saveConfig]);

  const handleTogglePanel = useCallback((panelClass, visible) => {
    if (!config) {
      return;
    }

    const entry = config.find((c) => c.panelClass === panelClass);

    if (entry) {
      const newConfig = config.map((c) =>
        c.panelClass === panelClass ? { ...c, visible } : c
      );
      saveConfig(newConfig);
    } else {
      const panel = getPanelInfo(panelClass);
      const newEntry = {
        panelClass,
        sort: config.length,
        width: panel ? panel.defaultWidth : 'single',
        refreshInterval: panel ? panel.defaultRefreshInterval : 0,
        visible: true,
      };
      saveConfig([...config, newEntry]);
    }
  }, [config, saveConfig, getPanelInfo]);

  if (loading) {
    return (
      <div className="dashboard">
        <div className="dashboard-panel__loading">Loading dashboard…</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="dashboard">
        <div className="dashboard-panel__error">{error}</div>
      </div>
    );
  }

  return (
    <div className="dashboard">
      <div className="dashboard__toolbar">
        <button
          type="button"
          className="dashboard__toolbar-btn"
          onClick={() => setSelectorOpen(true)}
        >
          Manage panels
        </button>
      </div>
      <div className="dashboard__grid">
        {visiblePanels.map((panelConfig, index) => {
          const info = getPanelInfo(panelConfig.panelClass);

          if (!info) {
            return null;
          }

          return (
            <DashboardPanel
              key={panelConfig.panelClass}
              panelClass={panelConfig.panelClass}
              title={info.title}
              resourceURL={info.resourceURL}
              width={panelConfig.width}
              refreshInterval={panelConfig.refreshInterval}
              index={index}
              onDragStart={handleDragStart}
              onDragOver={(e) => e.preventDefault()}
              onDrop={handleDrop}
            />
          );
        })}
      </div>
      {selectorOpen && (
        <PanelSelector
          availablePanels={availablePanels}
          config={config || []}
          onToggle={handleTogglePanel}
          onClose={() => setSelectorOpen(false)}
        />
      )}
    </div>
  );
}

