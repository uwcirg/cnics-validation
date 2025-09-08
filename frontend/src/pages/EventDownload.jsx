import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { showToast } from '../components/Toast'

function EventDownload() {
  const [searchParams] = useSearchParams()
  const [isDownloading, setIsDownloading] = useState(false)
  const [downloadError, setDownloadError] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''
  const eventId = searchParams.get('event_id')

  const handleDownload = async () => {
    if (!eventId) return
    
    setIsDownloading(true)
    setDownloadError(null)
    
    try {
      const url = `${apiUrl}/api/events/download/${eventId}`
      
      // Create a temporary link element for download
      const link = document.createElement('a')
      link.href = url
      link.download = '' // Let the server determine the filename
      link.style.display = 'none'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      
      showToast('Download started successfully', 'success')
    } catch (error) {
      console.error('Download failed:', error)
      setDownloadError('Failed to start download. Please try the manual link below.')
      showToast('Download failed. Please try the manual link.', 'error')
    } finally {
      setIsDownloading(false)
    }
  }

  useEffect(() => {
    if (!eventId) return
    
    // Auto-start download after a short delay
    const timer = setTimeout(() => {
      handleDownload()
    }, 500)
    
    return () => clearTimeout(timer)
  }, [apiUrl, eventId])

  if (!eventId) return <p>Missing event_id.</p>

  return (
    <div style={{ padding: '20px', maxWidth: '600px', margin: '0 auto' }}>
      <h1>📥 Download Event Files</h1>
      <p>Event ID: <strong>{eventId}</strong></p>
      
      {isDownloading && (
        <div style={{
          padding: '12px',
          backgroundColor: '#d1ecf1',
          border: '1px solid #bee5eb',
          borderRadius: '4px',
          marginBottom: '16px'
        }}>
          <strong>⏳ Downloading...</strong> Please wait while your file downloads.
        </div>
      )}
      
      {downloadError && (
        <div style={{
          padding: '12px',
          backgroundColor: '#f8d7da',
          border: '1px solid #f5c6cb',
          borderRadius: '4px',
          marginBottom: '16px',
          color: '#721c24'
        }}>
          <strong>❌ Error:</strong> {downloadError}
        </div>
      )}
      
      <div style={{
        padding: '16px',
        backgroundColor: '#f8f9fa',
        border: '1px solid #dee2e6',
        borderRadius: '4px',
        marginBottom: '16px'
      }}>
        <h3>📋 Download Options</h3>
        <p>If the download didn't start automatically, you can:</p>
        <ul>
          <li>Click the button below to retry the download</li>
          <li>Use the manual download link</li>
          <li>Check your browser's download folder</li>
        </ul>
      </div>
      
      <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
        <button
          onClick={handleDownload}
          disabled={isDownloading}
          style={{
            padding: '10px 20px',
            backgroundColor: isDownloading ? '#6c757d' : '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: isDownloading ? 'not-allowed' : 'pointer',
            fontSize: '14px'
          }}
        >
          {isDownloading ? '⏳ Downloading...' : '🔄 Retry Download'}
        </button>
        
        <a
          href={`${apiUrl}/api/events/download/${eventId}`}
          target="_blank"
          rel="noopener noreferrer"
          style={{
            padding: '10px 20px',
            backgroundColor: '#28a745',
            color: 'white',
            textDecoration: 'none',
            borderRadius: '4px',
            fontSize: '14px',
            display: 'inline-block'
          }}
        >
          🔗 Manual Download Link
        </a>
      </div>
      
      <div style={{
        marginTop: '20px',
        padding: '12px',
        backgroundColor: '#fff3cd',
        border: '1px solid #ffeaa7',
        borderRadius: '4px',
        fontSize: '14px',
        color: '#856404'
      }}>
        <strong>💡 Tip:</strong> The file will download with its original format (PDF, DOC, DOCX, or ZIP). 
        Make sure you have the appropriate software installed to open the file type.
      </div>
    </div>
  )
}

export default EventDownload


