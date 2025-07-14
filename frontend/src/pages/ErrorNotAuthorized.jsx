function ErrorNotAuthorized({ authUsername, controller, action }) {
  return (
    <div>
      <h1>Not Authorized User</h1>
      <p>{authUsername} is not authorized to access {controller}/{action}</p>
    </div>
  )
}

export default ErrorNotAuthorized
