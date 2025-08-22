import { useNavigate } from 'react-router-dom'

function ErrorNoReviewAccess() {
  const navigate = useNavigate()
  return (
    <div>
      <h1>Not Authorized for Reviewing</h1>
      <p>Your account does not have reviewer permissions.</p>
      <button onClick={() => navigate(-1)}>Go Back</button>
    </div>
  )
}

export default ErrorNoReviewAccess


