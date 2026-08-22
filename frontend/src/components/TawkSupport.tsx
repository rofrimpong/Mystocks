import { useEffect, useMemo } from 'react';
import { useLocation } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

declare global {
  interface Window {
    Tawk_API?: {
      visitor?: { name: string; email: string };
      customStyle?: {
        zIndex?: number | string;
        visibility?: {
          desktop?: { position: string; xOffset: number; yOffset: number };
          mobile?: { position: string; xOffset: number; yOffset: number };
        };
      };
      onLoad?: () => void;
      showWidget?: () => void;
      hideWidget?: () => void;
      shutdown?: () => void;
      setAttributes?: (attributes: Record<string, string>, callback?: (error?: unknown) => void) => void;
    };
    Tawk_LoadStart?: Date;
  }
}

const PROPERTY_ID = '69e51464ea80971c35af3d43';
const WIDGET_ID = '1jmjdfb74';
const SCRIPT_ID = 'cnmg-tawk-widget';

export default function TawkSupport() {
  const { pathname } = useLocation();
  const { user, businesses, currentBusinessId, currentBusinessRole } = useAuthStore();
  const business = useMemo(
    () => businesses.find((item) => item.id === currentBusinessId) || businesses[0],
    [businesses, currentBusinessId]
  );
  const shouldHide = pathname === '/sales' || pathname.startsWith('/sales/');

  useEffect(() => {
    if (!user || document.getElementById(SCRIPT_ID)) return;

    window.Tawk_API = window.Tawk_API || {};
    window.Tawk_API.customStyle = {
      zIndex: 35,
      visibility: {
        desktop: { position: 'br', xOffset: 20, yOffset: 20 },
        mobile: { position: 'br', xOffset: 12, yOffset: 82 },
      },
    };
    window.Tawk_API.visitor = { name: user.name, email: user.email };
    window.Tawk_LoadStart = new Date();
    window.Tawk_API.onLoad = () => {
      window.Tawk_API?.setAttributes?.({
        business_name: business?.name || 'Unknown business',
        business_id: business?.id || 'unknown',
        business_role: currentBusinessRole || business?.role || 'staff',
        cnmg_user_id: user.id,
      });
      if (window.location.pathname === '/sales' || window.location.pathname.startsWith('/sales/')) {
        window.Tawk_API?.hideWidget?.();
      } else {
        window.Tawk_API?.showWidget?.();
      }
    };

    const script = document.createElement('script');
    script.id = SCRIPT_ID;
    script.async = true;
    script.src = `https://embed.tawk.to/${PROPERTY_ID}/${WIDGET_ID}`;
    script.charset = 'UTF-8';
    script.setAttribute('crossorigin', '*');
    document.head.appendChild(script);

    return () => {
      window.Tawk_API?.hideWidget?.();
      window.Tawk_API?.shutdown?.();
    };
  }, [user, business?.id, business?.name, business?.role, currentBusinessRole]);

  useEffect(() => {
    if (shouldHide) window.Tawk_API?.hideWidget?.();
    else window.Tawk_API?.showWidget?.();
  }, [shouldHide]);

  return null;
}
