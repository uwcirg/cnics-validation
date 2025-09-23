import { Link } from 'react-router-dom'
import './MenuBar.css'

function MenuBar({ admin, uploader, reviewer, third_reviewer }) {
  return (
    <nav className="menu-bar">
      {/* Study Navigation */}
      <Link to="/">MCI Home</Link>
      <Link to="/vte">VTE Home</Link>
      
      {/* Admin Tools - only for admin users */}
      {admin && (
        <Link to="/admin">Admin Tools</Link>
      )}
      
      {/* Upload New Packets - for uploaders and admins */}
      {(uploader || admin) && (
        <Link to="/events/upload">Upload New Packets</Link>
      )}
      
      {/* Re-upload Existing Packets - for uploaders and admins */}
      {(uploader || admin) && (
        <Link to="/events/reupload">Re-upload Existing Packets</Link>
      )}
      
      {/* Review Events - for reviewers and admins */}
      {(reviewer || admin) && (
        <Link to="/events/review">Review Events</Link>
      )}
    </nav>
  )
}

export default MenuBar
