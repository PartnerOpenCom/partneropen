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
  title: 'Privacy notice · PartnerOpen Cloud',
  description: 'Privacy notice for PartnerOpen Connector and the stateless PartnerOpen Cloud site.',
};

export default function PrivacyPage() {
  return (
    <main className="site-shell legal-page">
      <nav className="legal-nav" aria-label="Legal navigation">
        <Link className="brand" href="/">
          <span className="brand-mark">P</span>
          <span>PartnerOpen</span>
        </Link>
        <div className="legal-nav-links">
          <Link className="text-link" href="/terms">Terms</Link>
          <Link className="back-link" href="/">Back to Cloud</Link>
        </div>
      </nav>

      <header className="legal-header">
        <p className="eyebrow">PARTNEROPEN CLOUD / LEGAL</p>
        <h1>Privacy notice</h1>
        <p className="legal-lede">
          This notice describes the PartnerOpen Connector and its optional connection to the stateless PartnerOpen Cloud public site. The Connector is the durable system of record in M1: connection state, consent records, Spaces, published snapshots and aggregate daily click counters remain in the WordPress database.
        </p>
        <p className="legal-updated">Last updated: {legalLastUpdated}</p>
      </header>

      <div className="legal-layout">
        <article className="legal-document">
          <section id="scope-catalog" className="legal-section" aria-labelledby="privacy-scope-title">
            <p className="eyebrow">01 / SCOPE CATALOG</p>
            <h2 id="privacy-scope-title">Consent is granular and explicit</h2>
            <p>
              The site owner chooses a prefix and partner email during setup, reviews the scope details and explicitly grants each scope. <code>{scopeIds.cloud_connection}</code> and <code>{scopeIds.content_sync}</code> are required for pairing and snapshot publication respectively. <code>{scopeIds.partner_email}</code> is optional at the option level but required to send an invitation; the other scopes are optional.
            </p>

            <div className="legal-table-wrap">
              <table className="legal-scope-table">
                <caption>Exact Connector consent metadata</caption>
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
                        <span>{scope.label}</span>
                        <small>{scope.required ? 'Required' : 'Optional'}</small>
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
            <p>{agentPackWithdrawal} The Space page and same-origin resolver continue under their Global Pause and Space-status rules.</p>
          </section>

          <section id="never-collected" className="legal-section" aria-labelledby="privacy-never-title">
            <p className="eyebrow">02 / NEVER COLLECTED</p>
            <h2 id="privacy-never-title">Aggregate measurement, not visitor dossiers</h2>
            <p>
              The Connector does not collect or send the following:
            </p>
            <ul className="legal-never-list">
              {neverCollected.map((item) => <li key={item}><span aria-hidden="true">×</span>{item}</li>)}
            </ul>
            <p>
              Measurements are aggregate daily counts per placement. There is no per-visitor analytics, fingerprinting or hidden identifier in a resolver request. A destination service may process a visitor after a redirect under that service&apos;s own notice; the Connector does not add visitor identity to the redirect.
            </p>
          </section>

          <section id="local-cloud" className="legal-section" aria-labelledby="privacy-local-title">
            <p className="eyebrow">03 / LOCAL AND CLOUD DATA</p>
            <h2 id="privacy-local-title">Local-first by construction</h2>
            <p>{localFirstGuarantee}</p>
            <p>
              Once paired, the Connector sends only the payload allowed by the granted scope and the signed API contract. The site secret is stored in a non-autoloaded WordPress option, returned exactly once during pairing and never included in REST status, snapshots or public agent files.
            </p>
            <p>{retentionSummary}</p>
            <p>
              Agent files are assembled from a positive public field allowlist, so link destinations, contact addresses, identifiers, service or payout data and unknown fields are dropped rather than filtered out. In M1, PartnerOpen Cloud keeps no snapshot, metrics or agent-file copy: the Connector is the store of record and there is no Cloud ingest endpoint.
            </p>
          </section>

          <section id="withdrawal" className="legal-section" aria-labelledby="privacy-withdrawal-title">
            <p className="eyebrow">04 / WITHDRAWAL AND DISCONNECT</p>
            <h2 id="privacy-withdrawal-title">Two independent owner controls</h2>
            <ul className="legal-list">
              {ownerControls.map((control) => <li key={control}><strong>{control}</strong></li>)}
            </ul>
            <p>{disconnectSemantics}</p>
            <p>
              Withdrawal of an optional scope is enforced at its own boundary. The owner can explicitly delete local snapshots separately. No Cloud-side snapshot, metrics or agent-file copy is stored in this M1; future service data is governed by that service&apos;s notice.
            </p>
            <p>
              Global Pause temporarily overlays all public Space, resolver and agent routes with a no-store 404. It does not delete state or revoke consent; resuming restores the retained published snapshot when the Space is active.
            </p>
          </section>

          <section id="hosts-and-milestones" className="legal-section" aria-labelledby="privacy-hosts-title">
            <p className="eyebrow">05 / CLOUD BOUNDARY</p>
            <h2 id="privacy-hosts-title">A narrow host allowlist and a deferred editor</h2>
            <p>
              Cloud pairing accepts only these hosts: {cloudHostAllowlist.map((host, index) => <span key={host}>{index > 0 ? ', ' : ''}<code>{host}</code></span>)}. The Cloud site is stateless and has no tenant database, hosted editor, pairing store, publish store or metrics-ingest store in this milestone.
            </p>
            <p>{deferredEditorStatement}</p>
            <p>{resolverDisclosure}</p>
          </section>

          <section id="responsibility" className="legal-section" aria-labelledby="privacy-responsibility-title">
            <p className="eyebrow">06 / OWNER RESPONSIBILITY</p>
            <h2 id="privacy-responsibility-title">Review the boundary and your destinations</h2>
            <p>{ownerResponsibility}</p>
          </section>
        </article>

        <aside className="legal-aside" aria-label="Privacy summary">
          <div className="legal-aside-card">
            <p className="eyebrow">PRIVACY AT A GLANCE</p>
            <dl className="legal-facts">
              <div><dt>Outbound HTTP</dt><dd>Zero before cloud consent</dd></div>
              <div><dt>Measurement</dt><dd>Date · placement · count</dd></div>
              <div><dt>Snapshot</dt><dd>Local until replaced or deleted</dd></div>
              <div><dt>Clicks</dt><dd>90 days, daily prune</dd></div>
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
        <span><Link className="text-link" href="/terms">terms</Link> · <Link className="text-link" href="/">home</Link></span>
      </footer>
    </main>
  );
}
