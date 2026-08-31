'use client';

import { useEffect, useId, useRef, useState } from 'react';

const focusable = 'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])';

type M2RequestDialogProps = {
  triggerLabel?: string;
  triggerArrow?: string;
};

export default function M2RequestDialog({ triggerLabel = 'See M2 scope', triggerArrow = '↗' }: M2RequestDialogProps) {
  const [open, setOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  const descriptionId = useId();

  useEffect(() => {
    if (!open) return;

    const previous = document.activeElement as HTMLElement | null;
    const dialog = dialogRef.current;
    const first = dialog?.querySelector<HTMLElement>(focusable);
    first?.focus();
    document.body.style.overflow = 'hidden';

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false);
        return;
      }
      if (event.key !== 'Tab' || !dialog) return;
      const elements = Array.from(dialog.querySelectorAll<HTMLElement>(focusable));
      if (elements.length === 0) return;
      const firstElement = elements[0];
      const lastElement = elements[elements.length - 1];
      if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
      } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
      }
    };

    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = '';
      previous?.focus();
      triggerRef.current?.focus();
    };
  }, [open]);

  return (
    <>
      <button
        ref={triggerRef}
        className="button button-secondary m2-request-trigger"
        type="button"
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() => setOpen(true)}
      >
        {triggerLabel} <span>{triggerArrow}</span>
      </button>
      <small className="m2-request-label">Milestone 2 · by request</small>
      {open ? (
        <div
          className="m2-dialog-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setOpen(false);
          }}
        >
          <div
            ref={dialogRef}
            className="m2-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            aria-describedby={descriptionId}
          >
            <div className="m2-dialog__top">
              <div>
                <p className="eyebrow">MILESTONE 2 / BY REQUEST</p>
                <h2 id={titleId}>A publishing network for multi-Host Creator distribution.</h2>
              </div>
              <button className="m2-dialog__close" type="button" aria-label="Close M2 scope dialog" onClick={() => setOpen(false)}>×</button>
            </div>
            <p id={descriptionId} className="m2-dialog__lead">Milestone 2 adds network-level administration and distribution for multiple Host Sites. The package is available by request; this panel explains the boundary and does not submit a request.</p>
            <p className="m2-dialog__payment"><strong>PartnerOpen does not process payments.</strong> The Connector, MCP and GUI provide publishing data and a basis for the agreed share calculation. Settlement follows the legal agreement between the participating parties.</p>
            <div className="m2-dialog__columns">
              <div>
                <p className="eyebrow">NETWORK PACKAGE</p>
                <ul className="m2-dialog__list">
                  <li>Organization, Site and Space hierarchy</li>
                  <li>Multi-Host network administration and audit timeline</li>
                  <li>Publishing network distribution across Host Sites</li>
                  <li>Shared network health and failure visibility</li>
                  <li>Connector event sync with safe metadata</li>
                  <li>Credential rotation and operational controls</li>
                </ul>
              </div>
              <div className="m2-dialog__boundary">
                <p className="eyebrow">AVAILABILITY</p>
                <p><strong>Available now:</strong> GPL Connector, host-controlled public Space, consent scopes, Global Pause, signed publishing, MCP Connector and PartnerOpen GUI.</p>
                <p><strong>By request:</strong> the network package described here, with multi-Host administration and distribution.</p>
                <p className="m2-dialog__note">The CTA opens an informational scope panel; no form or contact data is submitted here.</p>
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
