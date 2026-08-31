import Link from 'next/link';
import HeroPhonePreview from './components/HeroPhonePreview';
import M2RequestDialog from './components/M2RequestDialog';
import { connectorManifest } from './connector-manifest';
import { consentScopes } from './consent-scopes';

const heroAdvantages = [
  ['01', 'SAFE', 'Thoughtful participant protection, explicit consent and a visible WordPress boundary.'],
  ['02', 'EASY TO SET', 'Install the GPL Connector without external tooling or services on the Host side.'],
  ['03', 'FULL ADMIN LOGS', 'Audit connection, access, consent, publication and pause events without visitor dossiers.'],
] as const;

const workflow = [
  ['01', 'Install', 'Install the GPL PartnerOpen Connector in WordPress. Safe, transparent and local by default; no external tooling or services required on the Host side.'],
  ['02', 'Set the Space and Creator', 'Choose a prefix and onboard a named Creator directly by email. No open registration, separate database or OAuth integration is required on your side.'],
  ['03', 'Agree the boundary', 'Review the consent scopes, owner controls and collaboration terms before any connected data exchange.'],
  ['04', 'Publish by signed API, MCP or GUI', 'The Creator publishes through the signed API, the simple MCP Connector or PartnerOpen GUI.'],
  ['05', 'Keep global control and trace', 'The Host keeps Global Pause, consent withdrawal and disconnect. PartnerOpen keeps full admin audit logs, not visitor-level logs.'],
] as const;

const ownerDoes = [
  'Install the Connector and set a prefix and Creator contact during setup.',
  'Grant or withdraw each consent scope with its purpose and retention visible.',
  'Turn Global Pause on or off for the public Connector surface.',
  'Withdraw consent and disconnect the site; local snapshots remain until explicitly deleted.',
] as const;

const ownerDoesNot = [
  "Edit the Creator's Space content, links or agent-context files.",
  "Operate the Creator's account or browser editor.",
  'Manage individual Space publishing controls or Creator operations.',
  'Receive a per-visitor profile, visitor-level event stream or hidden destination data.',
  'Delete administrative audit proofs through normal content operations.',
] as const;

const neverCollected = [
  'cookies',
  'IP addresses',
  'user agents',
  'device fingerprints',
  'unique visitor identifiers',
  'visitor-level click events',
] as const;

const collaborationRoles = [
  ['HOST', 'Provides the page to the Creator and works on their own website.'],
  ['CREATOR', 'Brings the content and the commercial relationship to the page.'],
] as const;

const trustProof = [
  ['01', 'WordPress owns durable state', 'Pairing, consent, Space, snapshots and aggregate counters stay in WordPress.', '#consent', 'See scopes and storage'],
  ['02', 'Independent Creator interface', 'PartnerOpen hosts the collaborator control panel and dashboards. No need to host additional data, flows or security tooling on your side.', '#how-it-works', 'See the setup path'],
  ['03', 'Pause or disconnect', 'Global Pause hides public routes. Disconnect revokes the Connector secret and requires fresh pairing.', '#owner-control', 'See owner controls'],
  ['04', 'No visitor tracking provided', 'No cookies, IP addresses, fingerprints, visitor IDs or visitor-level click events. Just verified aggregate usage data.', '#never-collected', 'See the data boundary'],
] as const;

const integrationCards = [
  ['01', 'Partner-facing Space', 'Publish a branded directory, partner profiles and approved content without changing the systems that run your business.', 'PUBLIC SURFACE'],
  ['02', 'PRM endpoint', 'Use PartnerOpen as a lightweight endpoint layer for the partner-facing surface while your existing PRM keeps its operational workflows.', 'ENDPOINT-FIRST'],
  ['03', 'CRM connection', 'Keep pipeline, ownership and revenue operations in the CRM you already use; PartnerOpen stays focused on the Space.', 'STACK-ALIGNED'],
] as const;

const comparisonRows = [
  ['Primary job', 'Host-controlled partner Space and lightweight PRM endpoint layer.', 'Public directory backed by partner data and PRM workflows.', 'Public partner directory builder.'],
  ['Public profiles and directory', 'Public Space, profiles and approved partner content.', 'Public partner profiles, search and filters.', 'Partner listings and profile pages.'],
  ['Partner self-service and approval', 'Creator publishes through the signed API, MCP or GUI; Host controls the boundary.', 'Partner profile self-edit with approval.', 'Partner-owned listings.'],
  ['PRM / CRM role', 'Connects to the existing PRM and CRM instead of replacing them.', 'Directory and partner-management workflow.', 'Directory-first; referral capture is central.'],
  ['Host control', 'Scopes, visibility, Global Pause, consent and disconnect.', 'Directory and PRM configuration with approvals.', 'Directory configuration and listing management.'],
  ['Deployment / ownership model', 'WordPress Connector plus a host-controlled public surface.', 'Managed directory and PRM product.', 'Branded directory product on the customer domain.'],
  ['Best fit', 'Own the partner Space and connect the existing stack.', 'Directory plus partner operations.', 'Launch a public partner directory quickly.'],
] as const;

const agentFiles = ['AGENTS.md', 'llms.txt', 'ai-context.json', 'manifest.json', 'sitemap.xml'] as const;

const faqs = [
  [
    'Does PartnerOpen host a partner editor today?',
    'Yes. PartnerOpen GUI is available as a Creator-facing publication surface alongside the signed API and MCP Connector. The public landing remains stateless; the GUI carries the external collaboration experience.',
  ],
  [
    'What does the Connector store?',
    'The Connector stores pairing, consent, Space, published snapshot and aggregate counter state in WordPress options. WordPress remains the durable system of record for the public runtime.',
  ],
  [
    'Can the Creator publish without a Host step?',
    'Yes. After setup and the required consent scopes, the Creator publishes through the signed API, MCP or PartnerOpen GUI. The Host keeps Global Pause, consent withdrawal and disconnect as the public overlay.',
  ],
  [
    'Are links redirected invisibly?',
    'No. Every outbound link uses a same-origin resolver, shows a disclosure and renders rel="sponsored nofollow noopener". Raw external href values are not emitted by the Connector renderer.',
  ],
  [
    'What happens when consent is withdrawn?',
    'The Connector stops outbound exchange, revokes its site secret and marks the connection disconnected. The latest local published snapshot stays in WordPress until the owner explicitly deletes it.',
  ],
  [
    'Does PartnerOpen process payments?',
    'No. PartnerOpen provides publishing data and the basis for an agreed share calculation. Payments and settlement remain between the Host, Creator or network under their legal agreement.',
  ],
  [
    'What can Milestone 2 (M2) include?',
    'Milestone 2 is the publishing network package: multi-Host distribution, Organization → Site → Space administration, network health and the network-level audit flow. It is available by request; MCP and PartnerOpen GUI are available publication paths now.',
  ],
  [
    'Is Milestone 2 available now?',
    'The publishing network package is available by request through the Connect with us to discuss path. The base Connector, signed API, MCP Connector and PartnerOpen GUI are available now.',
  ],
  [
    'How is a Creator invited?',
    'The Site admin enters the Creator email, assigns Site or Space access, and sends an invitation. The Creator enters through a short-lived passwordless magic link; there is no open registration.',
  ],
  [
    'What do the full admin logs contain?',
    'The audit timeline contains safe administrative events such as connection, Creator access, consent, draft, publication, pause and disconnect results. It does not contain cookies, IP addresses, visitor profiles or visitor-level activity.',
  ],
] as const;

export default function HomePage() {
  return (
    <main className="site-shell">
      <nav className="topbar" aria-label="Primary navigation">
        <Link className="brand" href="/"><span className="brand-mark">P</span><span>PartnerOpen</span></Link>
        <div className="topbar-links">
          <a href="#how-it-works">How to connect</a>
          <a href="#where">Use cases</a>
          <Link href="/join">Join</Link>
        </div>
        <a className="topbar-download" href="/partneropen-connector-0.1.0.zip" download>Get WordPress Connector <span>↓</span></a>
      </nav>


      <section className="landing-screen hero-grid" id="hero" aria-labelledby="page-title">
        <div className="hero-copy">
          <p className="eyebrow">SET PARTNER SAFE SPACE VIA GPL WORDPRESS CONNECTOR</p>
          <h1 id="page-title">Partner directory. WordPress control.</h1>
          <p className="hero-lede">PartnerOpen is a GPL WordPress Connector that makes a Host-controlled partner Space on your website. Collaborate with the best Creators while the Host keeps full control. Connect to the existing PRM (Partner Relationship Management) and CRM (Client Relationship Management), or use aggregate placement metrics to keep the operational workflows you already run.</p>
          <div className="hero-status-rail" aria-label="PartnerOpen product status">
            <span className="hero-status-rail__live"><i /> M1 / AVAILABLE NOW</span>
            <span>WORDPRESS-OWNED</span>
            <span>CONSENT-FIRST</span>
            <span>ANY CREATOR</span>
          </div>

          <div className="hero-actions">
            <a className="button button-primary" href="/partneropen-connector-0.1.0.zip" download>Download Connector v0.1.0 <span>↓</span></a>
            <a className="button button-ghost" href="#where">See the Partner Network offering <span>↘</span></a>
          </div>
          <ul className="hero-usp" aria-label="PartnerOpen advantages">
            {heroAdvantages.map(([number, title, copy]) => (
              <li key={title}><span className="hero-usp__number">{number}</span><div><strong>{title}</strong><span>{copy}</span></div></li>
            ))}
          </ul>
          <p className="hero-note"><span className="pulse" /> GPL-2.0-OR-LATER · FREE FOR THE USER · ANY COMMERCIAL TERMS · NO VISITOR DOSSIERS</p>
        </div>
        <HeroPhonePreview />
      </section>

      <section className="landing-screen trust-proof-screen" id="trust-proof" aria-labelledby="trust-proof-title">
        <div className="section-heading"><div><p className="eyebrow">00 / TRUST YOU CAN VERIFY. FOR BOTH PARTIES</p><h2 id="trust-proof-title">Trusted Space via transparent collaboration.</h2></div><span className="section-index">TRUST</span></div>
        <p className="section-lede">PartnerOpen provides a safe place to work: content stays controlled by the owner while the Creator keeps publication freedom. Host controls the site boundary, not Creator content. Logs, a kill switch, safe Spaces and thoughtful participant protection make the trust boundary visible to both parties.</p>
        <div className="trust-proof-grid">
          {trustProof.map(([number, title, copy, href, label]) => (
            <article className="trust-proof-card" key={number}>
              <span className="trust-proof-card__number">{number}</span>
              <h3>{title}</h3>
              <p>{copy}</p>
              <a className="text-link" href={href}>{label} <span>↘</span></a>
            </article>
          ))}
        </div>
        <p className="trust-proof-links">Technical evidence: <a href="/api/connector-manifest">Connector manifest ↗</a> · <a href="/api/health">health endpoint ↗</a> · <a href="#consent">full consent scopes ↘</a> · <a href="/privacy">privacy boundary ↗</a></p>
      </section>

      <section className="landing-screen collaboration-screen" id="collaboration" aria-labelledby="collaboration-title">
        <div className="section-heading"><div><p className="eyebrow">00 / SAFE COLLABORATION</p><h2 id="collaboration-title">Different roles. One trusted space.</h2></div><span className="section-index">00—10</span></div>
        <div className="collaboration-grid">
          {collaborationRoles.map(([role, copy]) => <article className="collaboration-role" key={role}><span className="collaboration-role__number">{role}</span><p>{copy}</p></article>)}
        </div>
        <p className="section-lede collaboration-note"><strong>PartnerOpen provides the framework, consented publication records and verified aggregate data.</strong> Payment execution, legal terms and settlement remain between the participating parties under their own agreement, using that transparent data basis.</p>
        <p className="section-lede collaboration-together"><strong>Together: grow traffic and get mutual benefits from it.</strong></p>
      </section>

      <section className="landing-screen comparison-screen" id="comparison" aria-labelledby="comparison-title">
        <div className="section-heading"><div><p className="eyebrow">01 / PARTNER SPACE + PRM ENDPOINT</p><h2 id="comparison-title">Pro? Connect your PRM and CRM — without replacing them.</h2></div><span className="section-index">01—10</span></div>
        <p className="section-lede">Use PartnerOpen as the partner-facing framework for your ecosystem or set a new one. Publish the right partner Space, control access and visibility, and connect it to the PRM and CRM tools you already use.</p>
        <div className="integration-grid" aria-label="PRM and CRM connection paths">
          {integrationCards.map(([number, title, copy, status]) => (
            <article className="integration-card" key={number}>
              <div className="integration-mark">{number}</div>
              <h3>{title}</h3>
              <p>{copy}</p>
              <span className="integration-status">{status}</span>
            </article>
          ))}
        </div>
        <p className="integration-tools">Designed to work alongside <a href="https://www.partnero.com/features/partner-portal" target="_blank" rel="noopener noreferrer">Partnero</a>, <a href="https://www.introw.io/product" target="_blank" rel="noopener noreferrer">Introw</a> and <a href="https://partnerstack.com/" target="_blank" rel="noopener noreferrer">PartnerStack</a> or use PartnerOpen MCP.</p>
        <p className="positioning-line">PartnerOpen: light tooling to the partnership networks world.</p>
        <div className="value-strip" aria-label="PartnerOpen value propositions">
          <span><b>01</b><strong>SIMPLE INSTALL</strong></span>
          <span><b>02</b><strong>NO EXTERNAL TOOLING</strong></span>
          <span><b>03</b><strong>FREE FOR THE USER</strong></span>
          <span><b>04</b><strong>ANY COMMERCIAL TERMS</strong></span>
        </div>
        <div className="comparison-table-wrap">
          <table className="comparison-table">
            <thead><tr><th scope="col">Dimension</th><th scope="col">PartnerOpen</th><th scope="col"><a href="https://www.kiflo.com/product/partner-directory" target="_blank" rel="noopener noreferrer">Kiflo Partner Directory ↗</a></th><th scope="col"><a href="https://partnerpage.io/" target="_blank" rel="noopener noreferrer">PartnerPage.io ↗</a></th></tr></thead>
            <tbody>{comparisonRows.map(([dimension, partnerOpen, kiflo, partnerPage]) => <tr key={dimension}><th scope="row">{dimension}</th><td>{partnerOpen}</td><td>{kiflo}</td><td>{partnerPage}</td></tr>)}</tbody>
          </table>
        </div>
        <p className="comparison-note">A positioning comparison based on public product material, not a claim that one product replaces the others. Confirm current features and pricing with each provider.</p>
      </section>

      <section className="landing-screen signal-screen" id="how-it-works" aria-labelledby="workflow-title">
        <div className="section-heading"><div><p className="eyebrow">02 / EASY WORK WITH PARTNEROPEN</p><h2 id="workflow-title">A simple WordPress setup for a durable, flexible framework.</h2></div><span className="section-index">02—10</span></div>
        <div className="workflow-list">{workflow.map(([number, title, copy]) => <article className="workflow-row" key={number}><span className="signal-number">{number}</span><div><h3>{title}</h3><p>{copy}</p></div></article>)}</div>
      </section>
      <section className="landing-screen owner-screen" id="owner-control" aria-labelledby="owner-title">
        <div className="section-heading"><div><p className="eyebrow">03 / HOST CONTROL / CREATOR FREEDOM</p><h2 id="owner-title">Full control for the Host. Freedom for the Creator.</h2></div><span className="section-index">03—10</span></div>
        <p className="section-lede owner-boundary">Owner does not moderate, partially delete, hide or change Creator data: the control is Global Pause.</p>
        <div className="owner-layout">
          <div className="control-stack">
            <div className="switch-panel">
              <div className="switch-row"><span className="switch-indicator on" aria-hidden="true" /><div><h3>Global Pause</h3><p>One switch hides every public Connector route under the configured prefix. It does not erase local data, and it does not let the Host edit Creator information.</p></div><b>OWNER</b></div>
              <div className="switch-row"><span className="switch-indicator" aria-hidden="true" /><div><h3>Withdraw consent &amp; disconnect</h3><p>Stops data exchange, revokes the Connector secret and requires fresh pairing and consent to reconnect.</p></div><b>OWNER</b></div>
            </div>
            <div className="creator-control"><p className="eyebrow">CREATOR CONTROL</p><h3>Withdraw consent &amp; disconnect</h3><p>Creator can withdraw their consent and disconnect their participation. Connected Creator data is hidden until fresh consent is given.</p><b>CREATOR</b></div>
          </div>
          <div className="owner-columns">
            <div><p className="eyebrow">THE OWNER MANAGES</p><ul>{ownerDoes.map((item) => <li key={item}><span className="check">✓</span>{item}</li>)}</ul></div>
            <div><p className="eyebrow">THE OWNER DOES NOT MANAGE</p><ul>{ownerDoesNot.map((item) => <li key={item}><span className="check muted-check">—</span>{item}</li>)}</ul></div>
          </div>
        </div>
      </section>

      <section className="landing-screen consent-screen" id="consent" aria-labelledby="consent-title">
        <div className="section-heading"><div><p className="eyebrow">04 / SAFE, TRANSPARENT, LOCAL</p><h2 id="consent-title">Every scope has a recipient and a retention rule.</h2></div><span className="section-index">04—10</span></div>
        <p className="section-lede">Every consented exchange has a purpose, named recipient and retention rule. Start local, review the boundary, then grant only what the collaboration needs.</p>
        <div className="scope-overline" aria-label="Consent scope summary">
          <span>06 SCOPES</span>
          <span>LOCAL BY DEFAULT</span>
          <span>RECIPIENT + RETENTION</span>
        </div>
        <div className="scope-grid" aria-label="Consent scope cards">
          {consentScopes.map((scope) => (
            <article className={`scope-card ${scope.required ? 'scope-card--required' : ''}`} key={scope.id}>
              <div className="scope-card__top"><code>{scope.id}</code><span className={`scope-state ${scope.required ? 'required' : 'optional'}`}>{scope.required ? 'Required' : 'Optional'}</span></div>
              <h3>{scope.label}</h3>
              <p>{scope.purpose}</p>
              <dl className="scope-facts"><div><dt>FIELDS</dt><dd>{scope.fields.join(' · ')}</dd></div><div><dt>RECIPIENT</dt><dd>{scope.recipient}</dd></div><div><dt>RETENTION</dt><dd>{scope.retention}</dd></div></dl>
            </article>
          ))}
        </div>
        <p className="never-note"><strong>Never collected:</strong> {neverCollected.join(', ')}.</p>
      </section>

      <section className="landing-screen transparency-screen" id="links" aria-labelledby="links-title">
        <div className="section-heading"><div><p className="eyebrow">05 / LINK TRANSPARENCY</p><h2 id="links-title">A visible path from placement to destination.</h2></div><span className="section-index">05—10</span></div>
        <div className="transparency-layout">
          <div className="resolver-card"><div className="resolver-card__head"><span>SAME-ORIGIN RESOLVER</span><span className="console-live">NO CLOAKING</span></div><div className="resolver-path"><code>/partneropen/go/l1/hero</code><span>→</span><code className="resolver-destination">https://destination.example/path</code></div><p>The rendered HTML points to the same-origin resolver. The resolver checks pause state, Space status, link status, HTTPS allowlists and placement scope before redirecting.</p></div>
          <div className="transparency-copy"><p>Each outbound link includes a visible disclosure and <code>rel="sponsored nofollow noopener"</code>. The Connector never emits a raw external <code>href</code> from a snapshot and never hides the commercial relationship.</p><ul><li><span className="check">✓</span>Disclosure travels with the link</li><li><span className="check">✓</span>Resolver can return 404 without redirecting</li><li><span className="check">✓</span>Destination host is validated before use</li></ul></div>
        </div>
      </section>

      <section className="landing-screen agent-screen" id="agent-pack" aria-labelledby="agent-title">
        <div className="section-heading"><div><p className="eyebrow">06 / AGENT-CONTEXT PACK</p><h2 id="agent-title">Canonical files for a public Space.</h2></div><span className="section-index">06—10</span></div>
        <div className="agent-layout">
          <div className="agent-files"><div className="agent-files__head"><span>PUBLIC OUTPUTS</span><span>ALLOWLISTED</span></div>{agentFiles.map((file, index) => <div className="agent-file" key={file}><span>{String(index + 1).padStart(2, '0')}</span><code>{file}</code><b>{file === 'AGENTS.md' ? 'canonical' : 'public'}</b></div>)}</div>
          <div className="agent-copy"><p>The Connector generates <strong>AGENTS.md</strong> as the canonical agent-context file. <code>/agents.md</code> is only a 308 alias. The pack also includes <code>llms.txt</code>, <code>ai-context.json</code>, <code>manifest.json</code> and <code>sitemap.xml</code>.</p><p>The pack is assembled from a positive field allowlist: each section and block type contributes only named public fields — Space slug, title and status, SEO title, description and canonical, block headings, labels, questions, answers, columns and rows, plus resolver identifiers. Everything else, including destinations, emails, identifiers, payout data and unknown future keys, is dropped rather than filtered, and a deny-list pass remains only as defence in depth.</p><a className="text-link" href="/api/connector-manifest">Read Connector manifest <span>↗</span></a></div>
        </div>
      </section>

      <section className="landing-screen measurement-screen" id="never-collected" aria-labelledby="measurement-title">
        <div className="section-heading"><div><p className="eyebrow">07 / NEVER COLLECTED BY PARTNEROPEN</p><h2 id="measurement-title">Keep visitors safe. Just data.</h2></div><span className="section-index">07—10</span></div>
        <p className="section-lede">PartnerOpen reports only verified aggregate usage data. No visitor dossier, hidden audience layer or visitor-level activity is created.</p>
        <div className="measurement-layout"><div className="counter-card"><div className="counter-card__head"><span>AGGREGATE_METRICS</span><span className="console-live">90-DAY RETENTION</span></div><div className="counter-values"><div><strong>date</strong><small>YYYY-MM-DD</small></div><div><strong>placement</strong><small>identifier</small></div><div><strong>count</strong><small>integer total</small></div></div><p>PartnerOpen can provide aggregate placement data as a basis for the agreed share calculation. It does not process payments; settlement follows the legal agreement between participants.</p></div><div className="measurement-copy"><p className="eyebrow">NO VISITOR TRACKING PROVIDED</p><ul>{neverCollected.map((item) => <li key={item}><span className="check muted-check">—</span>{item}</li>)}</ul><p className="measurement-note">Just proven usage data, without a hidden audience layer.</p></div></div>
      </section>

      <section className="landing-screen where-screen" id="where" aria-labelledby="where-title">
        <div className="section-heading"><div><p className="eyebrow">08 / TWO USE CASES</p><h2 id="where-title">PartnerOpen use cases.</h2></div><span className="section-index">08—10</span></div>
        <div className="milestone-grid collaboration-use-cases">
          <article className="milestone-card live"><div className="milestone-card__top"><span className="milestone-tag">USE CASE 01 / AVAILABLE NOW</span><span className="console-live">PACKAGE v0.1.0</span></div><h3>Host collaborators for free and take a share.</h3><ul><li><strong>Set Connector + get Creators:</strong> the Host keeps durable pairing, consent, Spaces, snapshots and counters in WordPress.</li><li><strong>This site:</strong> landing, health, consent-scope and Connector-manifest APIs; no tenant database.</li><li><strong>Reference client:</strong> pair, sign and publish snapshots, then read status and aggregate metrics.</li><li><strong>Creator paths:</strong> signed API, MCP Connector and PartnerOpen GUI are available after the Host sets the boundary.</li></ul><p className="collaboration-use-case-note">PartnerOpen provides the data and share-calculation basis. Payments and settlement remain outside PartnerOpen under the legal agreement with the network or direct participant.</p><a className="text-link" href="#publish">See signed publish flow <span>↗</span></a></article>
          <article className="milestone-card deferred">
            <div className="milestone-card__top"><span className="milestone-tag">USE CASE 02 / MILESTONE 2 / BY REQUEST</span><span className="scope-state optional">NETWORK</span></div>
            <h3>Set the publishing network.</h3>
            <p>Get Hosts for your content at scale: work with any number of Hosts simultaneously, assign Creators through the Organization → Site → Space hierarchy, and review the administrative event trail through the PartnerOpen network UX.</p>
            <M2RequestDialog triggerLabel="Connect with us to discuss" triggerArrow="↗" />
          </article>
        </div>
      </section>

      <section className="landing-screen publish-screen" id="publish" aria-labelledby="publish-title">
        <div className="section-heading"><div><p className="eyebrow">09 / SIGNED API / MCP / GUI</p><h2 id="publish-title">Publish safely. Choose your surface.</h2></div><span className="section-index">09—10</span></div>
        <div className="publish-layout"><div className="publish-steps"><article><span>01</span><div><h3>Pair once</h3><p><code>POST /wp-json/partneropen/v1/pair</code> consumes a one-time code and returns the Connector secret once.</p></div></article><article><span>02</span><div><h3>Signed API</h3><p><code>PUT /spaces/{'{space}'}/snapshot</code> sends the canonical typed snapshot with HMAC-SHA256 headers.</p></div></article><article><span>03</span><div><h3>MCP Connector</h3><p>Use the simple MCP Connector as an agent-facing publication path while the Host keeps the WordPress boundary.</p></div></article><article><span>04</span><div><h3>PartnerOpen GUI</h3><p>Invite a Creator by email, scope access to Sites or Spaces, publish through the external UX and inspect full admin audit logs.</p></div></article></div><div className="publish-console"><span className="console-label">SIGNED PUBLICATION</span><strong>VERIFIED</strong><code>x-partneropen-site</code><code>timestamp + nonce + signature</code><small>WordPress confirms the published snapshot.</small></div></div>
      </section>

      <section className="landing-screen install-screen" id="install" aria-labelledby="install-title">
        <div className="section-heading"><div><p className="eyebrow">INSTALL THE CONNECTOR</p><h2 id="install-title">Start with the GPL package.</h2></div><span className="section-index">GET STARTED</span></div>
        <div className="install-layout"><div className="install-copy"><p>Download the <strong>partneropen-connector-0.1.0.zip</strong> release package, then upload it in WordPress under Plugins → Add New → Upload Plugin. Activate it, set a prefix and Creator contact, review the six scopes, and connect without external tooling or services on the Host side.</p><div className="hero-actions"><a className="button button-primary" href="/partneropen-connector-0.1.0.zip" download>Download Connector v0.1.0 <span>↓</span></a><a className="button button-ghost" href="/api/connector-manifest">Inspect Connector manifest <span>↗</span></a></div><a className="text-link install-checksum" href="/partneropen-connector-0.1.0.zip.sha256" download>Verify SHA-256 checksum ↗</a></div><div className="install-card"><div><span className="console-label">PACKAGE</span><strong>partneropen-connector-0.1.0.zip</strong></div><div><span className="console-label">LICENSE</span><strong>GPL-2.0-or-later</strong></div><div><span className="console-label">REQUIRES</span><strong>WordPress 6.5 · PHP 8.1</strong></div><div><span className="console-label">VERSION</span><strong>{connectorManifest.version}</strong></div></div></div>
      </section>

      <section className="landing-screen faq-screen" id="faq" aria-labelledby="faq-title">
        <div className="section-heading"><div><p className="eyebrow">QUESTIONS / ANSWERS</p><h2 id="faq-title">Straight answers about the boundary.</h2></div></div>
        <div className="faq-grid">{faqs.map(([question, answer]) => <details className="faq" key={question}><summary>{question}<span>+</span></summary><p>{answer}</p></details>)}</div>
      </section>

      <footer className="footer"><span>PartnerOpen Cloud / public site · v{connectorManifest.version}</span><span>Connector owns durable state · <Link className="text-link" href="/api/health">health</Link> · <Link className="text-link" href="/api/consent-scopes">scopes</Link> · <Link className="text-link" href="/terms">Terms</Link> · <Link className="text-link" href="/privacy">Privacy</Link></span></footer>
    </main>
  );
}
