import { ImageResponse } from 'next/og';

export const alt = 'PartnerOpen — GPL WordPress Connector for Partner Spaces & PRM';
export const size = { width: 1200, height: 630 };
export const contentType = 'image/png';

export default function OpenGraphImage() {
  return new ImageResponse(
    (
      <div
        style={{
          width: '100%',
          height: '100%',
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between',
          background: 'linear-gradient(135deg, #07090d 0%, #101820 100%)',
          color: '#f4f6f2',
          padding: '72px 80px',
          fontFamily: 'sans-serif',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
          <div
            style={{
              width: '64px',
              height: '64px',
              borderRadius: '12px',
              background: '#f1f0e8',
              color: '#0b1016',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '40px',
              fontWeight: 700,
            }}
          >
            P
          </div>
          <div style={{ fontSize: '34px', fontWeight: 600, letterSpacing: '0.02em' }}>PartnerOpen</div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
          <div style={{ fontSize: '68px', fontWeight: 700, lineHeight: 1.1, maxWidth: '980px' }}>
            Partner directory. WordPress control.
          </div>
          <div style={{ fontSize: '28px', color: '#9ba7ad', maxWidth: '900px', lineHeight: 1.4 }}>
            GPL WordPress Connector for Host-controlled partner Spaces. Consent-first Creator
            collaboration, signed API and MCP. No visitor tracking.
          </div>
        </div>

        <div style={{ display: 'flex', gap: '28px', fontSize: '22px', color: '#9ba7ad', letterSpacing: '0.08em' }}>
          <span>GPL-2.0-OR-LATER</span>
          <span>·</span>
          <span>M1 / AVAILABLE NOW</span>
          <span>·</span>
          <span>partneropen.com</span>
        </div>
      </div>
    ),
    { ...size },
  );
}
