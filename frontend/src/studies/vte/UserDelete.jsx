import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { showToast } from '../../components/Toast'

function VTEUserDelete() {
  const [searchParams] = useSearchParams()
  const userId = searchParams.get('id')
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  const handleDelete = async () => {
    if (!userId) {
      setStatus('error')
      return
    }
    
    setStatus('deleting')
    try {
      const res = await fetch(`${apiUrl}/api/users/${userId}?study=vte`, {
        method: 'DELETE',
        credentials: 'include',
      })
      
      if (res.ok) {
        setStatus('deleted')
        showToast('VTE user deleted successfully.', 'success')
      } else if (res.status === 401) {
        setStatus('unauthorized')
        showToast('Login required.', 'warning')
      } else if (res.status === 403) {
        setStatus('forbidden')
        showToast('Not authorized to delete user.', 'error')
      } else {
        setStatus('error')
        showToast('Failed to delete VTE user.', 'error')
      }
    } catch {
      setStatus('error')
      showToast('Network error while deleting user.', 'error')
    }
  }

  if (!userId) {
    return (
      <div>
        <h1>Delete VTE User</h1>
        <p>Missing user ID.</p>
      </div>
    )
  }

  return (
    <div>
      <h1>Delete VTE User</h1>
      <p>Are you sure you want to delete user ID: <strong>{userId}</strong>?</p>
      
      <div style={{ marginTop: '20px' }}>
        <button 
          onClick={handleDelete} 
          disabled={status === 'deleting'}
          style={{
            padding: '10px 20px',
            backgroundColor: status === 'deleting' ? '#6c757d' : '#dc3545',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: status === 'deleting' ? 'not-allowed' : 'pointer',
            marginRight: '10px'
          }}
        >
          {status === 'deleting' ? 'Deleting...' : 'Delete User'}
        </button>
        
        <a 
          href="/vte/users/viewAll"
          style={{
            padding: '10px 20px',
            backgroundColor: '#6c757d',
            color: 'white',
            textDecoration: 'none',
            borderRadius: '4px',
            display: 'inline-block'
          }}
        >
          Cancel
        </a>
      </div>

      {status === 'deleting' && <p>Deleting user...</p>}
      {status === 'deleted' && <p>User deleted successfully.</p>}
      {status === 'error' && <p>Failed to delete user.</p>}
    </div>
  )
}

export default VTEUserDelete
