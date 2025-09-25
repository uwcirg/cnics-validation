import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { showToast } from '../../components/Toast'

function VTEUsersViewAll() {
  const [users, setUsers] = useState([])
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  useEffect(() => {
    async function loadUsers() {
      try {
        const res = await fetch(`${apiUrl}/api/tables/users?study=vte&limit=1000`, { credentials: 'include' })
        if (res.ok) {
          const json = await res.json()
          setUsers(json.data || [])
        } else if (res.status === 401) {
          setStatus('unauthorized')
          showToast('Login required.', 'warning')
        } else if (res.status === 403) {
          setStatus('forbidden')
          showToast('Not authorized to view users.', 'error')
        } else {
          setStatus('error')
          showToast('Failed to load users.', 'error')
        }
      } catch {
        setStatus('error')
        showToast('Network error while loading users.', 'error')
      }
    }
    loadUsers()
  }, [apiUrl])

  return (
    <div>
      <h1>VTE Users</h1>
      
      <div style={{ marginBottom: '20px' }}>
        <Link 
          to="/vte/users/add"
          style={{
            padding: '10px 20px',
            backgroundColor: '#007bff',
            color: 'white',
            textDecoration: 'none',
            borderRadius: '4px',
            display: 'inline-block'
          }}
        >
          Add New VTE User
        </Link>
      </div>

      {status === 'unauthorized' && <p>Login required to view users.</p>}
      {status === 'forbidden' && <p>Not authorized to view users.</p>}
      {status === 'error' && <p>Failed to load users.</p>}

      {users.length > 0 && (
        <table className="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Login</th>
              <th>First Name</th>
              <th>Last Name</th>
              <th>Site</th>
              <th>Uploader</th>
              <th>Reviewer</th>
              <th>3rd Reviewer</th>
              <th>Admin</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {users.map((user) => (
              <tr key={user.id}>
                <td>{user.id}</td>
                <td>{user.username}</td>
                <td>{user.login}</td>
                <td>{user.first_name}</td>
                <td>{user.last_name}</td>
                <td>{user.site}</td>
                <td>{user.uploader_flag ? 'Yes' : 'No'}</td>
                <td>{user.reviewer_flag ? 'Yes' : 'No'}</td>
                <td>{user.third_reviewer_flag ? 'Yes' : 'No'}</td>
                <td>{user.admin_flag ? 'Yes' : 'No'}</td>
                <td>
                  <div style={{ display: 'flex', gap: '8px' }}>
                    <Link 
                      to={`/vte/users/edit?id=${user.id}`}
                      style={{
                        padding: '4px 8px',
                        backgroundColor: '#28a745',
                        color: 'white',
                        textDecoration: 'none',
                        borderRadius: '3px',
                        fontSize: '12px'
                      }}
                    >
                      Edit
                    </Link>
                    <Link 
                      to={`/vte/users/delete?id=${user.id}`}
                      style={{
                        padding: '4px 8px',
                        backgroundColor: '#dc3545',
                        color: 'white',
                        textDecoration: 'none',
                        borderRadius: '3px',
                        fontSize: '12px'
                      }}
                    >
                      Delete
                    </Link>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {users.length === 0 && status !== 'error' && status !== 'unauthorized' && status !== 'forbidden' && (
        <p>No VTE users found.</p>
      )}
    </div>
  )
}

export default VTEUsersViewAll
