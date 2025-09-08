import { Link } from 'react-router-dom'
import './Admin.css'

function Admin() {
  return (
    <div className="admin-container">
      <h1>Admin Tools</h1>
      <p>Administrative functions for managing events and users.</p>

      <section>
        <h3>Events</h3>
        <div>
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
            <li>
              <Link to="/events/export">Export all events as CSV</Link>
            </li>
          </ul>
        </div>
      </section>

      <section>
        <h3>Users</h3>
        <div>
          <ul>
            <li>
              <Link to="/users/add">Add a user</Link>
            </li>
            <li>
              <Link to="/users/viewAll">Edit/Delete users</Link>
            </li>
          </ul>
        </div>
      </section>
    </div>
  )
}

export default Admin
