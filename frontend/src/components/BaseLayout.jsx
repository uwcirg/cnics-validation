import { Link } from 'react-router-dom'
import './BaseLayout.css'
import MenuBar from './MenuBar'

function Toasts() {
  return <div id="toast-root" style={{ position: 'fixed', right: 16, bottom: 16, zIndex: 1000 }} />
}

function BaseLayout({ children, auth, study_title }) {
  const { admin = false, uploader = false, reviewer = false, third_reviewer = false, username } = auth || {}
  // Resolved banner study title, supplied by App (a STUDY_TITLE override, or a
  // title derived from the study type). Null until GET /api/config resolves
  // (or when no title applies) — the banner then shows the logo alone rather
  // than a broken "undefined Project".
  const studyTitle = study_title
  return (
    <div className="layout">
      <header className="site-banner">
        <Link to="/" className="banner-brand">
          <img className="banner-logo" src="/cnics_logo.png" alt="CNICS" />
          {studyTitle && <span className="banner-study-title">{studyTitle}</span>}
        </Link>
      </header>
      <div className="login-strip">
        {username ? (
          <span>You are logged in as: {username} | <Link to="/users/logout">Log Out</Link></span>
        ) : (
          <span />
        )}
      </div>
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
