import { useState } from 'react';
import { showToast } from './Toast';

/**
 * Development component for testing different authentication levels
 * Only appears in development mode when ALLOW_DEV_HEADER is enabled on the backend
 */
function DevAuthTester({ currentAuth, onAuthChange }) {
  const [isExpanded, setIsExpanded] = useState(false);
  const [testingUser, setTestingUser] = useState('');

  // Only show in development mode
  const isDev = !import.meta.env.PROD;
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '');

  if (!isDev) return null;

  // No predefined users; this tool now only toggles flags for the current user

  const refreshAuth = async () => {
    try {
      const res = await fetch(`${apiUrl}/api/auth/me`, { credentials: 'include' });
      if (res.ok) {
        const json = await res.json();
        onAuthChange(json.data || {});
        showToast('Auth refreshed', 'info');
      }
    } catch (_) {}
  };

  const toggleRole = async (roleKey, value) => {
    try {
      if (!currentAuth || !currentAuth.login) {
        showToast('No current user to update.', 'error');
        return;
      }
      const body = { login: currentAuth.login, [roleKey]: !!value };
      const res = await fetch(`${apiUrl}/api/auth/dev_set_roles`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body),
      });
      if (!res.ok) {
        if (res.status === 404) {
          showToast('User not found. Seed users or ensure your user exists.', 'error');
        } else {
          showToast('Failed to update roles.', 'error');
        }
        return;
      }
      const json = await res.json();
      onAuthChange(json.data || currentAuth);
      showToast('Updated roles.', 'info');
    } catch (e) {
      showToast('Failed to update roles.', 'error');
    }
  };

  return (
    <div style={{
      position: 'fixed',
      top: '20px',
      left: '20px',
      zIndex: 9999,
      backgroundColor: '#2c3e50',
      color: 'white',
      borderRadius: '8px',
      padding: isExpanded ? '16px' : '8px',
      boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
      minWidth: isExpanded ? '320px' : 'auto',
      maxWidth: '400px'
    }}>
      <div 
        style={{ 
          cursor: 'pointer', 
          display: 'flex', 
          alignItems: 'center', 
          gap: '8px',
          fontSize: '14px',
          fontWeight: 'bold'
        }}
        onClick={() => setIsExpanded(!isExpanded)}
      >
        🧪 Dev Auth Tester {isExpanded ? '▼' : '▶'}
      </div>
      
      {isExpanded && (
        <div style={{ marginTop: '12px' }}>
          <div style={{ fontSize: '12px', marginBottom: '12px', color: '#bdc3c7' }}>
            Current: {currentAuth.username || 'Not authenticated'}
            {currentAuth.username && (
              <div>
                Roles: {[
                  currentAuth.admin && 'Admin',
                  currentAuth.uploader && 'Uploader',
                  currentAuth.reviewer && 'Reviewer',
                  currentAuth.third_reviewer && 'Third Reviewer'
                ].filter(Boolean).join(', ') || 'None'}
              </div>
            )}
          </div>

          {/* Inline role toggles for the CURRENT user (dev-only) */}
          {currentAuth && currentAuth.username && (
            <div style={{
              display: 'grid',
              gridTemplateColumns: '1fr 1fr',
              gap: '6px 12px',
              alignItems: 'center',
              marginBottom: '12px',
              padding: '8px',
              background: '#22313f',
              borderRadius: '6px'
            }}>
              <div style={{ fontSize: '12px', opacity: 0.9 }}>Toggle Roles (for {currentAuth.login}):</div>
              <div></div>
              <label style={{ fontSize: '12px' }}>
                <input
                  type="checkbox"
                  checked={!!currentAuth.admin}
                  onChange={(e) => toggleRole('admin', e.target.checked)}
                  style={{ marginRight: '6px' }}
                />
                Admin
              </label>
              <label style={{ fontSize: '12px' }}>
                <input
                  type="checkbox"
                  checked={!!currentAuth.uploader}
                  onChange={(e) => toggleRole('uploader', e.target.checked)}
                  style={{ marginRight: '6px' }}
                />
                Uploader
              </label>
              <label style={{ fontSize: '12px' }}>
                <input
                  type="checkbox"
                  checked={!!currentAuth.reviewer}
                  onChange={(e) => toggleRole('reviewer', e.target.checked)}
                  style={{ marginRight: '6px' }}
                />
                Reviewer
              </label>
              <label style={{ fontSize: '12px' }}>
                <input
                  type="checkbox"
                  checked={!!currentAuth.third_reviewer}
                  onChange={(e) => toggleRole('third_reviewer', e.target.checked)}
                  style={{ marginRight: '6px' }}
                />
                Third Reviewer
              </label>
            </div>
          )}

          <div style={{ display: 'flex', gap: '8px', marginBottom: '10px' }}>
            <button
              onClick={refreshAuth}
              style={{
                padding: '6px 10px',
                backgroundColor: '#3498db',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
                fontSize: '12px'
              }}
            >
              🔄 Refresh Auth
            </button>
          </div>
          
          {/* No user switching buttons anymore */}
          
          <div style={{ 
            fontSize: '11px', 
            color: '#95a5a6', 
            marginTop: '12px',
            borderTop: '1px solid #34495e',
            paddingTop: '8px'
          }}>
            ⚠️ Development only. Requires ALLOW_DEV_HEADER=1 on backend.
          </div>
        </div>
      )}
    </div>
  );
}

export default DevAuthTester;
