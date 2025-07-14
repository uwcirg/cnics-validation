function ErrorUnknownUser({ authUsername }) {
  return (
    <div>
      <h1>Unknown User</h1>
      <p>Unknown user: {authUsername}</p>
    </div>
  )
}

export default ErrorUnknownUser
