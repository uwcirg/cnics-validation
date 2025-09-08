import { Link } from 'react-router-dom'
import './BaseLayout.css'
import MenuBar from './MenuBar'

function Toasts() {
  return <div id="toast-root" style={{ position: 'fixed', right: 16, bottom: 16, zIndex: 1000 }} />
}

function BaseLayout({ children, auth }) {
  const { admin = false, uploader = false, reviewer = false, third_reviewer = false, username } = auth || {}
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
      <MenuBar admin={admin} uploader={uploader} reviewer={reviewer} third_reviewer={third_reviewer} />
      <main>
        {children}
        <footer>
          <Link to="/">Home page</Link>
        </footer>
      </main>
      <Toasts />
    </div>
  )
}

export default BaseLayout
