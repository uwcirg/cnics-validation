import { Link } from 'react-router-dom'
import { useEffect, useRef } from 'react'
import './MenuBar.css'

function MenuBar({ admin, uploader, reviewer }) {
  const menuRef = useRef(null)

  useEffect(() => {
    function handleClick(event) {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        menuRef.current
          .querySelectorAll('details[open]')
          .forEach((detail) => detail.removeAttribute('open'))
      }
    }
    document.addEventListener('click', handleClick)
    return () => document.removeEventListener('click', handleClick)
  }, [])

  return (
    <nav className="menu-bar" ref={menuRef}>
      <ul>
        {admin && (
          <li>
            <details>
              <summary>Administrative Tools</summary>
              <ul>
                <li>
                  <Link to="/events/viewAll">View all events</Link>
                </li>
                <li>
                  <Link to="/events/add">Add an event</Link>
                </li>
                <li>
                  <Link to="/events/addMany">Add multiple events from a CSV file</Link>
                </li>
              </ul>
            </details>
          </li>
        )}

        {admin && (
          <li>
            <details>
              <summary>Users</summary>
              <ul>
                <li>
                  <Link to="/users/add">Add a user</Link>
                </li>
                <li>
                  <Link to="/users/viewAll">Edit/Delete users</Link>
                </li>
              </ul>
            </details>
          </li>
        )}

        {uploader && (
          <li>
            <details>
              <summary>Upload Packets</summary>
              <ul>
                <li>
                  <Link to="/events/upload">Upload New Packets</Link>
                </li>
                <li>
                  <Link to="/events/reupload">Re-upload Existing Packets</Link>
                </li>
              </ul>
            </details>
          </li>
        )}

        {reviewer && (
          <li>
            <details>
              <summary>Reviewer Tools</summary>
              <ul>
                <li>
                  <Link to="/events/review">Review Events</Link>
                </li>
              </ul>
            </details>
          </li>
        )}
      </ul>
    </nav>
  )
}

export default MenuBar
