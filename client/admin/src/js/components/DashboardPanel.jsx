import React, { useState, useEffect, useRef, useCallback } from 'react';

/**
 * Renders a single dashboard panel with title header, content loaded via fetch,
 * and optional auto-refresh.
 *
 * @param {Object} props
 * @param {string} props.panelClass - FQCN of the panel
 * @param {string} props.title - Panel title
 * @param {string} props.resourceURL - URL to load panel content
 * @param {string} props.width - 'single' or 'double'
 * @param {number} props.refreshInterval - Auto-refresh interval in seconds (0 = off)
 * @param {Function} props.onDragStart - Drag start handler
 * @param {Function} props.onDragOver - Drag over handler
 * @param {Function} props.onDrop - Drop handler
 * @param {number} props.index - Panel index for drag and drop
 */
export default function DashboardPanel({
  panelClass,
  title,
  resourceURL,
  width,
  refreshInterval,
  onDragStart,
  onDragOver,
  onDrop,
  index,
}) {
  const [content, setContent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const intervalRef = useRef(null);

  const loadContent = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(resourceURL, { credentials: 'same-origin' });

      if (!response.ok) {
        throw new Error('Failed to load panel content');
      }

      const text = await response.text();
      setContent(text);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [resourceURL]);

  useEffect(() => {
    loadContent();
  }, [loadContent]);

  useEffect(() => {
    if (refreshInterval > 0) {
      intervalRef.current = setInterval(loadContent, refreshInterval * 1000);
    }

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [refreshInterval, loadContent]);

  const className = [
    'dashboard-panel',
    width === 'double' ? 'dashboard-panel--double' : '',
    dragOver ? 'dashboard-panel--drag-over' : '',
  ].filter(Boolean).join(' ');

  const handleDragOver = (e) => {
    e.preventDefault();
    setDragOver(true);
    if (onDragOver) {
      onDragOver(e, index);
    }
  };

  const handleDragLeave = () => {
    setDragOver(false);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setDragOver(false);
    if (onDrop) {
      onDrop(e, index);
    }
  };

  const handleDragStart = (e) => {
    if (onDragStart) {
      onDragStart(e, index);
    }
  };

  const renderContent = () => {
    if (loading) {
      return <div className="dashboard-panel__loading">Loading…</div>;
    }

    if (error) {
      return <div className="dashboard-panel__error">{error}</div>;
    }

    return (
      <div
        className="dashboard-panel__content"
        dangerouslySetInnerHTML={{ __html: content }}
      />
    );
  };

  return (
    <div
      className={className}
      draggable
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDragLeave={handleDragLeave}
      onDrop={handleDrop}
    >
      <div className="dashboard-panel__header">{title}</div>
      {renderContent()}
    </div>
  );
}

