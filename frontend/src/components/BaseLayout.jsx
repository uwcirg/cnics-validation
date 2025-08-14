import { Link } from 'react-router-dom'
import './BaseLayout.css'
import MenuBar from './MenuBar'

function BaseLayout({ children, auth }) {
  const { admin = false, uploader = false, reviewer = false, username } = auth || {}
  return (
    <div className="layout">
      <header className="header">
        <div id="title">
          <Link to="/" className="brand">
            <img src="/files/cnics_logo.png" alt="CNICS logo" />
            <span>CNICS Validation</span>
          </Link>
        </div>
        <div id="login">
          {username ? (
            <span>You are logged in as: {username} | <Link to="/users/logout">Log Out</Link></span>
          ) : (
            <span />
          )}
        </div>
      </header>
      <MenuBar admin={admin} uploader={uploader} reviewer={reviewer} />
      <main>
        {children}
        <footer>
          <Link to="/">Home page</Link>
        </footer>
      </main>
    </div>
  )
}

export default BaseLayout
