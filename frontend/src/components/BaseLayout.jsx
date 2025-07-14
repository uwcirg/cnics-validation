import { Link } from 'react-router-dom'
import MenuBar from './MenuBar'
import './BaseLayout.css'

function BaseLayout({ children, auth }) {
  const { admin = false, uploader = false, reviewer = false, username } = auth || {}
  return (
    <div className="layout">
      <header className="header">
        <div id="title">
          <Link to="/">CNICS Validation</Link>
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
