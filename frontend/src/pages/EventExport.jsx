import { useEffect } from 'react'

function EventExport() {
  const apiUrl = import.meta.env.VITE_API_URL || ''

  useEffect(() => {
    const url = `${apiUrl}/api/events/export?format=csv`
    try {
      window.location.href = url
    } catch {
      // noop
    }
  }, [apiUrl])

  return (
    <div>
      <h1>Exporting Events CSV</h1>
      <p>If your download does not start automatically, click the link below:</p>
      <p>
        <a href={`${apiUrl}/api/events/export?format=csv`}>Download events CSV</a>
      </p>
    </div>
  )
}

export default EventExport


