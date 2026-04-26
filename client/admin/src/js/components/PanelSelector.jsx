import React from 'react';

/**
 * Modal overlay for selecting which panels are visible on the dashboard.
 *
 * @param {Object} props
 * @param {Array} props.availablePanels - All available panel definitions
 * @param {Array} props.config - Current panel configuration
 * @param {Function} props.onToggle - Callback when a panel is toggled (panelClass, visible)
 * @param {Function} props.onClose - Callback to close the modal
 */
export default function PanelSelector({ availablePanels, config, onToggle, onClose }) {
  const isVisible = (panelClass) => {
    const entry = config.find((c) => c.panelClass === panelClass);
    return entry ? entry.visible : false;
  };

  return (
    <div className="panel-selector" onClick={onClose}>
      <div className="panel-selector__dialog" onClick={(e) => e.stopPropagation()}>
        <h3 className="panel-selector__title">Manage panels</h3>
        {availablePanels.length === 0 ? (
          <p className="panel-selector__empty">No panels available.</p>
        ) : (
          <ul className="panel-selector__list">
            {availablePanels.map((panel) => (
              <li key={panel.identifier} className="panel-selector__item">
                <label className="panel-selector__label">
                  <input
                    type="checkbox"
                    checked={isVisible(panel.identifier)}
                    onChange={() => onToggle(panel.identifier, !isVisible(panel.identifier))}
                  />
                  {panel.title}
                </label>
              </li>
            ))}
          </ul>
        )}
        <div className="panel-selector__footer">
          <button
            type="button"
            className="panel-selector__close-btn"
            onClick={onClose}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
}

