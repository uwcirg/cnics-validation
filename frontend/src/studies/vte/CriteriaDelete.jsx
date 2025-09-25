import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { showToast } from '../../components/Toast'

function VTECriteriaDelete() {
  const [searchParams] = useSearchParams()
  const criteriaId = searchParams.get('id')
  const eventId = searchParams.get('event_id')
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  const handleDelete = async () => {
    if (!criteriaId) {
      setStatus('error')
      return
    }
    
    setStatus('deleting')
    try {
      const res = await fetch(`${apiUrl}/api/criteria/${criteriaId}?study=vte`, {
        method: 'DELETE',
        credentials: 'include',
      })
      
      if (res.ok) {
        setStatus('deleted')
        showToast('VTE criterion deleted successfully.', 'success')
      } else if (res.status === 401) {
        setStatus('unauthorized')
        showToast('Login required.', 'warning')
      } else if (res.status === 403) {
        setStatus('forbidden')
        showToast('Not authorized to delete criterion.', 'error')
      } else {
        setStatus('error')
        showToast('Failed to delete VTE criterion.', 'error')
      }
    } catch {
      setStatus('error')
      showToast('Network error while deleting criterion.', 'error')
    }
  }

  if (!criteriaId) {
    return (
      <div>
        <h1>Delete VTE Criterion</h1>
        <p>Missing criterion ID.</p>
      </div>
    )
  }

  return (
    <div>
      <h1>Delete VTE Criterion</h1>
      <p>Are you sure you want to delete criterion ID: <strong>{criteriaId}</strong>?</p>
      
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
          {status === 'deleting' ? 'Deleting...' : 'Delete Criterion'}
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

      {status === 'deleting' && <p>Deleting criterion...</p>}
      {status === 'deleted' && <p>Criterion deleted successfully.</p>}
      {status === 'error' && <p>Failed to delete criterion.</p>}
    </div>
  )
}

export default VTECriteriaDelete
