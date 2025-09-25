import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { showToast } from '../../components/Toast'

function VTESolicitationDelete() {
  const [searchParams] = useSearchParams()
  const solicitationId = searchParams.get('id')
  const eventId = searchParams.get('event_id')
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  const handleDelete = async () => {
    if (!solicitationId) {
      setStatus('error')
      return
    }
    
    setStatus('deleting')
    try {
      const res = await fetch(`${apiUrl}/api/solicitations/${solicitationId}?study=vte`, {
        method: 'DELETE',
        credentials: 'include',
      })
      
      if (res.ok) {
        setStatus('deleted')
        showToast('VTE solicitation deleted successfully.', 'success')
      } else if (res.status === 401) {
        setStatus('unauthorized')
        showToast('Login required.', 'warning')
      } else if (res.status === 403) {
        setStatus('forbidden')
        showToast('Not authorized to delete solicitation.', 'error')
      } else {
        setStatus('error')
        showToast('Failed to delete VTE solicitation.', 'error')
      }
    } catch {
      setStatus('error')
      showToast('Network error while deleting solicitation.', 'error')
    }
  }

  if (!solicitationId) {
    return (
      <div>
        <h1>Delete VTE Solicitation</h1>
        <p>Missing solicitation ID.</p>
      </div>
    )
  }

  return (
    <div>
      <h1>Delete VTE Solicitation</h1>
      <p>Are you sure you want to delete solicitation ID: <strong>{solicitationId}</strong>?</p>
      
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
          {status === 'deleting' ? 'Deleting...' : 'Delete Solicitation'}
        </button>
        
        <a 
          href={eventId ? `/vte/edit?id=${eventId}` : '/vte/viewAll'}
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

      {status === 'deleting' && <p>Deleting solicitation...</p>}
      {status === 'deleted' && <p>Solicitation deleted successfully.</p>}
      {status === 'error' && <p>Failed to delete solicitation.</p>}
    </div>
  )
}

export default VTESolicitationDelete
