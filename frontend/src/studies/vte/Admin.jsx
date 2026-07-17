import { Link } from 'react-router-dom'
import '../../pages/Admin.css'

function VTEAdmin() {
  return (
    <div className="admin-container">
      <h1>VTE Admin Tools</h1>
      <p>Administrative functions for managing VTE events and users.</p>

      <section>
        <h3>VTE Events</h3>
        <div>
          <ul>
            <li>
              <Link to="/vte/viewAll">View all VTE events</Link>
            </li>
            <li>
              <Link to="/vte/add">Add a VTE event</Link>
            </li>
            <li>
              <Link to="/vte/addMany">Add multiple VTE events from a CSV file</Link>
            </li>
            <li>
              <Link to="/vte/export">Export all VTE events as CSV</Link>
            </li>
            <li>
              <Link to="/vte/assignMany">Assign reviewers to VTE events</Link>
            </li>
            <li>
              <Link to="/vte/assignThird">Assign third reviewers to VTE events</Link>
            </li>
            <li>
              <Link to="/vte/sendMany">Send VTE charts to reviewers</Link>
            </li>
          </ul>
        </div>
      </section>

      <section>
        <h3>VTE Users</h3>
        <div>
          <ul>
            <li>
              <Link to="/vte/users/add">Add a VTE user</Link>
            </li>
            <li>
              <Link to="/vte/users/viewAll">Edit/Delete VTE users</Link>
            </li>
          </ul>
        </div>
      </section>

      <section>
        <h3>VTE Criteria & Solicitations</h3>
        <div>
          <ul>
            <li>
              <Link to="/vte/criteria/add">Add VTE criteria</Link>
            </li>
            <li>
              <Link to="/vte/solicitations/add">Add VTE solicitation</Link>
            </li>
          </ul>
        </div>
      </section>
    </div>
  )
}

export default VTEAdmin
