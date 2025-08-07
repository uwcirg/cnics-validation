import { Link } from 'react-router-dom'
import './MenuBar.css'

function MenuBar({ admin, uploader, reviewer }) {
  return (
    <nav className="menu-bar">
      {admin && <Link to="/">Admin Tools</Link>}
      {uploader && (
        <>
          <Link to="/events/upload">Upload New Packets</Link>
          <Link to="/events/reupload">Re-upload Existing Packets</Link>
        </>
      )}
      {reviewer && <Link to="/events/review">Review Events</Link>}
    </nav>
  )
}

export default MenuBar
