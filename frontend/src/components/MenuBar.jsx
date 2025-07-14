import { Link } from 'react-router-dom'
import './MenuBar.css'

function MenuBar({ admin, uploader, reviewer }) {
  return (
    <nav className="menu-bar">
      <ul>
        {admin && (
          <li>
            <Link to="/events">Admin Tools</Link>
          </li>
        )}
        {uploader && (
          <>
            <li>
              <Link to="/events/upload">Upload New Packets</Link>
            </li>
            <li>
              <Link to="/events/upload">Re-upload Existing Packets</Link>
            </li>
          </>
        )}
        {reviewer && (
          <li>
            <Link to="/events/review">Review Events</Link>
          </li>
        )}
      </ul>
    </nav>
  )
}

export default MenuBar
