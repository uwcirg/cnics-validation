import { useEffect } from 'react'
import '../../pages/Home.css'

function VTEEventExport() {
  const apiUrl = import.meta.env.VITE_API_URL || ''

  useEffect(() => {
    const url = `${apiUrl}/api/events/export?format=csv&study=vte`
    try {
      window.location.href = url
    } catch {
      // noop
    }
  }, [apiUrl])

  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      <h1>Exporting VTE Events CSV</h1>
      <p>If your download does not start automatically, click the link below:</p>
      <p>
        <a href={`${apiUrl}/api/events/export?format=csv&study=vte`}>Download VTE events CSV</a>
      </p>
    </div>
  )
}

export default VTEEventExport
