import { useState } from 'react'

function UserAdd() {
  const [formData, setFormData] = useState({
    username: '',
    login: '',
    first_name: '',
    last_name: '',
    site: '',
    uploader: false,
    reviewer: false,
    third_reviewer: false,
    admin: false,
  })
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  const handleChange = (e) => {
    const { name, type, checked, value } = e.target
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    try {
      const res = await fetch(`${apiUrl}/api/users`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(formData),
      })
      if (res.ok) {
        setStatus('saved')
        setFormData({
          username: '',
          login: '',
          first_name: '',
          last_name: '',
          site: '',
          uploader: false,
          reviewer: false,
          third_reviewer: false,
          admin: false,
        })
      } else {
        setStatus('error')
      }
    } catch {
      setStatus('error')
    }
  }

  return (
    <div>
      <h1>Add User</h1>
      <form onSubmit={handleSubmit}>
        <div>
          <label>
            Username:
            <input type="text" name="username" value={formData.username} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Login:
            <input type="text" name="login" value={formData.login} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            First Name:
            <input type="text" name="first_name" value={formData.first_name} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Last Name:
            <input type="text" name="last_name" value={formData.last_name} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Site:
            <input type="text" name="site" value={formData.site} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Upload packets?
            <input type="checkbox" name="uploader" checked={formData.uploader} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Reviewer?
            <input type="checkbox" name="reviewer" checked={formData.reviewer} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Possible 3rd Reviewer?
            <input type="checkbox" name="third_reviewer" checked={formData.third_reviewer} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Admin?
            <input type="checkbox" name="admin" checked={formData.admin} onChange={handleChange} />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
      {status === 'saved' && <p>User saved.</p>}
      {status === 'error' && <p>Failed to save user.</p>}
    </div>
  )
}

export default UserAdd
