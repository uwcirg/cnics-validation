import { Link } from 'react-router-dom'
import './MenuBar.css'

function MenuBar({ admin, uploader, reviewer }) {
  return (
    <nav className="menu-bar">
      <ul>
        {admin && (
          <li>
            <span>Administrative Tools</span>
            <ul>
              <li>
                <Link to="/events/viewAll">View all events</Link>
              </li>
              <li>
                <Link to="/events/add">Add an event</Link>
              </li>
              <li>
                <Link to="/events/addMany">Add multiple events from a CSV file</Link>
              </li>
            </ul>
          </li>
        )}

        {admin && (
          <li>
            <span>Users</span>
            <ul>
              <li>
                <Link to="/users/add">Add a user</Link>
              </li>
              <li>
                <Link to="/users/viewAll">Edit/Delete users</Link>
              </li>
            </ul>
          </li>
        )}

        {uploader && (
          <li>
            <span>Upload Packets</span>
            <ul>
              <li>
                <Link to="/events/upload">Upload New Packets</Link>
              </li>
              <li>
                <Link to="/events/reupload">Re-upload Existing Packets</Link>
              </li>
            </ul>
          </li>
        )}

        {reviewer && (
          <li>
            <span>Reviewer Tools</span>
            <ul>
              <li>
                <Link to="/events/review">Review Events</Link>
              </li>
            </ul>
          </li>
        )}
      </ul>
    </nav>
  )
}

export default MenuBar
