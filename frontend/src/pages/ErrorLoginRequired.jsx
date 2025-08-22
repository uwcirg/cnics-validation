import { useNavigate } from 'react-router-dom'

function ErrorLoginRequired() {
  const navigate = useNavigate()
  return (
    <div>
      <h1>Login Required</h1>
      <p>You must be logged in to access this page.</p>
      <button onClick={() => navigate(-1)}>Go Back</button>
    </div>
  )
}

export default ErrorLoginRequired


