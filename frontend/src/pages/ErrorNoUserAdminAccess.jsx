import { useNavigate } from 'react-router-dom'

function ErrorNoUserAdminAccess() {
  const navigate = useNavigate()
  return (
    <div>
      <h1>Not Authorized to Manage Users</h1>
      <p>Your account does not have admin permissions to add or edit users.</p>
      <button onClick={() => navigate(-1)}>Go Back</button>
    </div>
  )
}

export default ErrorNoUserAdminAccess


