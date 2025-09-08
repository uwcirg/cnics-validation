/**
 * UserStatus component displays the current user's roles and permissions
 * Useful for debugging and user awareness of their access levels
 */
function UserStatus({ auth }) {
  if (!auth || !auth.username) {
    return (
      <div style={{
        padding: '12px',
        backgroundColor: '#f8d7da',
        border: '1px solid #f5c6cb',
        borderRadius: '4px',
        color: '#721c24',
        fontSize: '14px'
      }}>
        <strong>⚠️ Not authenticated</strong>
        <div>Please log in to access the system.</div>
      </div>
    );
  }

  const roles = [];
  if (auth.admin) roles.push('Admin');
  if (auth.uploader) roles.push('Uploader');
  if (auth.reviewer) roles.push('Reviewer');
  if (auth.third_reviewer) roles.push('Third Reviewer');

  const roleColor = auth.admin ? '#d4edda' : roles.length > 0 ? '#d1ecf1' : '#fff3cd';
  const borderColor = auth.admin ? '#c3e6cb' : roles.length > 0 ? '#bee5eb' : '#ffeaa7';

  return (
    <div style={{
      padding: '12px',
      backgroundColor: roleColor,
      border: `1px solid ${borderColor}`,
      borderRadius: '4px',
      fontSize: '14px',
      marginBottom: '16px'
    }}>
      <strong>👤 Current User:</strong> {auth.username}
      <br />
      <strong>🔑 Roles:</strong> {roles.length > 0 ? roles.join(', ') : 'None assigned'}
      {auth.site && (
        <>
          <br />
          <strong>🏥 Site:</strong> {auth.site}
        </>
      )}
    </div>
  );
}

export default UserStatus
