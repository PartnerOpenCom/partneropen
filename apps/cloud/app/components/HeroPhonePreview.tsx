'use client';

import { useEffect, useRef } from 'react';

export default function HeroPhonePreview() {
  const sceneRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const scene = sceneRef.current;
    if (!scene) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const update = (event: PointerEvent) => {
      if (reduceMotion.matches) return;
      const bounds = scene.getBoundingClientRect();
      const x = (event.clientX - bounds.left) / bounds.width - 0.5;
      const y = (event.clientY - bounds.top) / bounds.height - 0.5;
      scene.style.setProperty('--parallax-x', `${(x * 18).toFixed(2)}px`);
      scene.style.setProperty('--parallax-y', `${(y * 14).toFixed(2)}px`);
      scene.style.setProperty('--phone-rotate', `${(x * 3).toFixed(2)}deg`);
    };
    const reset = () => {
      scene.style.setProperty('--parallax-x', '0px');
      scene.style.setProperty('--parallax-y', '0px');
      scene.style.setProperty('--phone-rotate', '1.5deg');
    };

    scene.addEventListener('pointermove', update);
    scene.addEventListener('pointerleave', reset);
    return () => {
      scene.removeEventListener('pointermove', update);
      scene.removeEventListener('pointerleave', reset);
    };
  }, []);

  return (
    <div ref={sceneRef} className="hero-phone-scene" aria-label="PartnerOpen WordPress Connector setup and administrative audit preview" role="img">
      <div className="hero-phone-orbit hero-phone-orbit--one" aria-hidden="true" />
      <div className="hero-phone-orbit hero-phone-orbit--two" aria-hidden="true" />
      <div className="hero-phone-glow" aria-hidden="true" />
      <div className="hero-phone">
        <div className="hero-phone__notch" aria-hidden="true" />
        <div className="hero-phone__screen">
          <div className="hero-phone__topline"><span>PARTNEROPEN / CONNECTOR</span><b>READY</b></div>
          <div className="hero-phone__heading"><span>WORDPRESS CONNECTOR</span><strong>Easy setup</strong></div>
          <div className="hero-phone__usp"><span>SAFE</span><span>EASY TO SET</span><span>FULL ADMIN LOGS</span></div>
          <div className="hero-phone__status"><i /> WordPress site connected <small>v0.1.0</small></div>
          <div className="hero-phone__checks"><div><span>✓</span><b>CONSENT GRANTED</b></div><div><span>✓</span><b>CREATOR INVITED</b></div></div>
          <div className="hero-phone__audit">
            <div className="hero-phone__audit-head"><span>FULL ADMIN LOGS</span><small>SAFE METADATA</small></div>
            <div className="hero-phone__event"><i /><div><strong>creator.invited</strong><small>Site admin · accepted</small></div><b>NOW</b></div>
            <div className="hero-phone__event"><i /><div><strong>snapshot.published</strong><small>Space / verified</small></div><b>02m</b></div>
            <div className="hero-phone__event hero-phone__event--warning"><i /><div><strong>global_pause.changed</strong><small>Host control · ready</small></div><b>14m</b></div>
          </div>
          <div className="hero-phone__footer"><span>NO VISITOR DATA</span><b>GLOBAL PAUSE</b></div>
        </div>
      </div>
      <div className="hero-phone-badge hero-phone-badge--top"><span className="pulse" /> SAFE · EASY TO SET</div>
      <div className="hero-phone-badge hero-phone-badge--bottom">FULL ADMIN LOGS · NO VISITOR DATA</div>
    </div>
  );
}
