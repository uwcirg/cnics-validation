import { Link, useLocation } from 'react-router-dom'
import './MenuBar.css'

function MenuBar({ admin, uploader, reviewer, third_reviewer }) {
  const location = useLocation()
  const isVtePage = location.pathname.startsWith('/vte')
  
  return (
    <nav className="menu-bar">
      {/* Admin Tools - only for admin users */}
      {admin && (
        <Link to={isVtePage ? "/vte/admin" : "/admin"}>Admin Tools</Link>
      )}

      {/* Assign Charts - reviewer-assignment page, only for admin users (FR-003).
          Always the shared, config-driven page; the /vte/* fork is stale tech
          debt and is not linked here (plan.md; Constitution Principle IV). */}
      {admin && (
        <Link to="/events/assignMany">Assign Charts</Link>
      )}

      {/* Upload New Packets - for uploaders and admins */}
      {(uploader || admin) && (
        <Link to={isVtePage ? "/vte/upload" : "/events/upload"}>Upload New Packets</Link>
      )}
      
      {/* Re-upload Existing Packets - for uploaders and admins */}
      {(uploader || admin) && (
        <Link to={isVtePage ? "/vte/reupload" : "/events/reupload"}>Re-upload Existing Packets</Link>
      )}
      
      {/* Review Events - for reviewers and admins */}
      {(reviewer || admin) && (
        <Link to={isVtePage ? "/vte/review" : "/events/review"}>Review Events</Link>
      )}
      
      {/* View All Events - for reviewers, uploaders and admins */}
      {(reviewer || uploader || admin) && (
        <Link to={isVtePage ? "/vte/viewAll" : "/events/viewAll"}>View All Events</Link>
      )}
    </nav>
  )
}

export default MenuBar
