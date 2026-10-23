import { useRef, useState } from 'react'
import { checkPacketAccess } from './packetAccess'

// A download link for an event's packet that says why a download is refused
// (403, 404) instead of saving the error as a file (spec 013 D4,
// contracts/ui.md "Errors surfaced").
function PacketDownloadLink({ href, style, children }) {
  const [error, setError] = useState('')
  // Set once the check passes, so the re-dispatched click downloads natively.
  const allowed = useRef(false)

  const handleClick = async (e) => {
    if (allowed.current) {
      allowed.current = false
      return
    }
    e.preventDefault()
    const anchor = e.currentTarget
    setError('')
    const message = await checkPacketAccess(href)
    if (message) {
      setError(message)
      return
    }
    allowed.current = true
    anchor.click()
  }

  return (
    <>
      <a href={href} download="" style={style} onClick={handleClick}>
        {children}
      </a>
      {error && (
        <span role="alert" style={{ color: '#721c24', marginLeft: '8px' }}>
          {error}
        </span>
      )}
    </>
  )
}

export default PacketDownloadLink
