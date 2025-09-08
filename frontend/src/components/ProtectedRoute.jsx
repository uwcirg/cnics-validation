import { Link } from 'react-router-dom';
import { useEffect } from 'react';
import { showToast } from './Toast';

/**
 * ProtectedRoute component that restricts access based on user roles
 * @param {Object} props
 * @param {React.ReactNode} props.children - The component to render if authorized
 * @param {string[]} props.requiredRoles - Array of roles that can access this route (e.g., ['admin'], ['reviewer', 'uploader'])
 * @param {Object} props.auth - Auth object containing user roles and info
 * @param {string} props.fallbackMessage - Custom message for unauthorized access
 */
function ProtectedRoute({ children, requiredRoles = [], auth = {}, fallbackMessage }) {
  // Check if user has at least one of the required roles
  const hasPermission = requiredRoles.length === 0 || requiredRoles.some(role => auth[role]);
  
  // Show toast notification when access is denied
  useEffect(() => {
    if (!hasPermission && auth.username) {
      const toastMessage = `Access denied: ${requiredRoles.join(' or ')} privileges required`;
      showToast(toastMessage, 'error', 4000);
    }
  }, [hasPermission, auth.username, requiredRoles]);
  
  if (!hasPermission) {
    const defaultMessage = `Access Denied: This page requires ${requiredRoles.join(' or ')} privileges.`;
    
    return (
      <div style={{
        padding: '40px',
        textAlign: 'center',
        backgroundColor: '#fff3cd',
        border: '1px solid #ffeaa7',
        borderRadius: '4px',
        margin: '20px'
      }}>
        <h2 style={{ color: '#856404', marginBottom: '16px' }}>🔒 Access Restricted</h2>
        <p style={{ color: '#856404', marginBottom: '20px' }}>
          {fallbackMessage || defaultMessage}
        </p>
        <div style={{ marginTop: '20px' }}>
          <Link 
            to="/" 
            style={{
              display: 'inline-block',
              padding: '8px 16px',
              backgroundColor: '#007bff',
              color: 'white',
              textDecoration: 'none',
              borderRadius: '4px'
            }}
          >
            Return to Home
          </Link>
        </div>
        {auth.username && (
          <div style={{ marginTop: '16px', fontSize: '14px', color: '#6c757d' }}>
            Currently logged in as: {auth.username}
            <br />
            Your roles: {[
              auth.admin && 'Admin',
              auth.uploader && 'Uploader', 
              auth.reviewer && 'Reviewer',
              auth.third_reviewer && 'Third Reviewer'
            ].filter(Boolean).join(', ') || 'None'}
          </div>
        )}
      </div>
    );
  }
  
  return children;
}

export default ProtectedRoute
