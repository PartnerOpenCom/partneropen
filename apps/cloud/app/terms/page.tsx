import Link from 'next/link';
import { consentScopes } from '../consent-scopes';
import { connectorManifest } from '../connector-manifest';
import {
  agentPackWithdrawal,
  cloudHostAllowlist,
  deferredEditorStatement,
  disconnectSemantics,
  formatScopeFields,
  legalLastUpdated,
  localFirstGuarantee,
  neverCollected,
  ownerControls,
  ownerResponsibility,
  resolverDisclosure,
  retentionSummary,
  scopeIds,
} from '../legal-content';

export const dynamic = 'force-static';

export const metadata = {
  title: 'Terms of use',
  description:
    'Terms of use for the PartnerOpen GPL WordPress Connector and the stateless PartnerOpen public site.',
  alternates: { canonical: '/terms' },
};

export default function TermsPage() {
  return (
    <main className="site-shell legal-page">
      <nav className="legal-nav" aria-label="Legal navigation">
        <Link className="brand" href="/">
          <span className="brand-mark">P</span>
          <span>PartnerOpen</span>
        </Link>
        <div className="legal-nav-links">
          <Link className="text-link" href="/privacy">Privacy</Link>
          <Link className="back-link" href="/">Back to Cloud</Link>
        </div>
      </nav>

      <header className="legal-header">
        <p className="eyebrow">PARTNEROPEN CLOUD / LEGAL</p>
        <h1>Terms of use</h1>
        <p className="legal-lede">
          These terms describe use of the GPL PartnerOpen Connector and the public PartnerOpen Cloud site in the M1 Connector MVP. The Connector stores durable state in the WordPress database; this Cloud site is stateless and does not provide a hosted partner editor or tenant backend.
        </p>
        <p className="legal-updated">Last updated: {legalLastUpdated}</p>
      </header>

      <div className="legal-layout">
        <article className="legal-document">
          <section id="consent" className="legal-section" aria-labelledby="terms-consent-title">
            <p className="eyebrow">01 / CONSENT AND DATA BOUNDARY</p>
            <h2 id="terms-consent-title">Owner controls and consent</h2>
            <p>
              The site owner installs the Connector, chooses a valid URL prefix, supplies a partner email when needed, reviews the six scopes and accepts the applicable privacy notice. <code>{scopeIds.partner_email}</code> is optional in the data structure but required to send an invitation. Consent is granular and every optional scope can be withdrawn independently.
            </p>
            <p>{localFirstGuarantee}</p>

            <div className="legal-table-wrap">
              <table className="legal-scope-table">
                <caption>Six consent scopes, their purpose, fields, recipient and retention</caption>
                <thead>
                  <tr>
                    <th scope="col">Scope</th>
                    <th scope="col">Purpose</th>
                    <th scope="col">Data fields</th>
                    <th scope="col">Recipient</th>
                    <th scope="col">Retention</th>
                  </tr>
                </thead>
                <tbody>
                  {consentScopes.map((scope) => (
                    <tr key={scope.id}>
                      <th scope="row">
                        <code>{scope.id}</code>
                        <span>{scope.required ? 'Required' : 'Optional'}</span>
                      </th>
                      <td>{scope.purpose}</td>
                      <td>{formatScopeFields(scope.fields)}</td>
                      <td>{scope.recipient}</td>
                      <td>{scope.retention}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <p className="legal-callout">
              <strong>Never collected:</strong> {neverCollected.join(', ')}.
            </p>
            <p>{agentPackWithdrawal} The Space page and same-origin resolver continue under their Global Pause and Space-status rules.</p>
          </section>

          <section id="publishing" className="legal-section" aria-labelledby="terms-publishing-title">
            <p className="eyebrow">02 / PUBLISHING AND LINKS</p>
            <h2 id="terms-publishing-title">A visible, same-origin path to every destination</h2>
            <p>
              In M1, a partner publishes through the signed <code>partneropen/v1</code> API, normally using the reference sync client. The Connector validates snapshots, limits block types, sanitizes rich text and stores the accepted version locally.
            </p>
            <p>{resolverDisclosure}</p>
            <p>
              Cloud API requests are accepted only from the host allowlist: {cloudHostAllowlist.map((host, index) => <span key={host}>{index > 0 ? ', ' : ''}<code>{host}</code></span>)}. The site owner is responsible for the destination and any additional relationship or legal notice required by its jurisdiction.
            </p>
          </section>

          <section id="controls" className="legal-section" aria-labelledby="terms-controls-title">
            <p className="eyebrow">03 / OWNER CONTROLS</p>
            <h2 id="terms-controls-title">Two controls, with no hidden control plane</h2>
            <ul className="legal-list">
              {ownerControls.map((control) => <li key={control}><strong>{control}</strong></li>)}
            </ul>
            <p>
              Global Pause is a separate publication overlay. It makes public Space pages, agent assets and resolver requests return a no-store 404; it does not delete state, revoke a partner or change the local snapshot. Resume restores an active published snapshot.
            </p>
            <p>{disconnectSemantics}</p>
            <p>
              Withdrawal of an optional scope takes effect at that scope&apos;s boundary. The owner can explicitly delete local snapshots separately; disconnect never silently removes the partner or claims to erase another service&apos;s data.
            </p>
          </section>

          <section id="retention" className="legal-section" aria-labelledby="terms-retention-title">
            <p className="eyebrow">04 / RETENTION AND MILESTONES</p>
            <h2 id="terms-retention-title">WordPress remains the durable system of record</h2>
            <p>{retentionSummary}</p>
            <p>
              The site secret is stored in a non-autoloaded WordPress option, returned exactly once during pairing and never included in REST status, snapshots or public agent files. PartnerOpen Cloud keeps no snapshot, metrics or agent-file copy in this milestone because there is no Cloud ingest endpoint.
            </p>
            <p>{deferredEditorStatement}</p>
          </section>

          <section id="responsibility" className="legal-section" aria-labelledby="terms-responsibility-title">
            <p className="eyebrow">05 / RESPONSIBILITY</p>
            <h2 id="terms-responsibility-title">Use the Connector lawfully</h2>
            <p>{ownerResponsibility}</p>
            <p>
              PartnerOpen does not guarantee ranking, placement, moderation, approval, conversions, revenue or availability of any external destination. Users must comply with the laws, terms and policies applicable to their sites and destinations.
            </p>
          </section>
        </article>

        <aside className="legal-aside" aria-label="Terms summary">
          <div className="legal-aside-card">
            <p className="eyebrow">QUICK FACTS</p>
            <dl className="legal-facts">
              <div><dt>Product</dt><dd>GPL-2.0-or-later Connector</dd></div>
              <div><dt>State</dt><dd>WordPress options</dd></div>
              <div><dt>Cloud</dt><dd>Stateless public site</dd></div>
              <div><dt>Controls</dt><dd>Global Pause + disconnect</dd></div>
            </dl>
          </div>
          <div className="legal-aside-card">
            <p className="eyebrow">CLOUD HOST ALLOWLIST</p>
            <ul className="legal-host-list">
              {cloudHostAllowlist.map((host) => <li key={host}><code>{host}</code></li>)}
            </ul>
          </div>
        </aside>
      </div>

      <footer className="footer legal-footer">
        <span>PartnerOpen Cloud / public site · v{connectorManifest.version}</span>
        <span><Link className="text-link" href="/privacy">privacy</Link> · <Link className="text-link" href="/">home</Link></span>
      </footer>
    </main>
  );
}
