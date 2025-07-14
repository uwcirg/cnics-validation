function UserAdd() {
  return (
    <div>
      <h1>Add User</h1>
      <form>
        <div>
          <label>
            Username:
            <input type="text" name="username" />
          </label>
        </div>
        <div>
          <label>
            Login:
            <input type="text" name="login" />
          </label>
        </div>
        <div>
          <label>
            First Name:
            <input type="text" name="first_name" />
          </label>
        </div>
        <div>
          <label>
            Last Name:
            <input type="text" name="last_name" />
          </label>
        </div>
        <div>
          <label>
            Site:
            <input type="text" name="site" />
          </label>
        </div>
        <div>
          <label>
            Upload packets?
            <input type="checkbox" name="uploader" />
          </label>
        </div>
        <div>
          <label>
            Reviewer?
            <input type="checkbox" name="reviewer" />
          </label>
        </div>
        <div>
          <label>
            Possible 3rd Reviewer?
            <input type="checkbox" name="third_reviewer" />
          </label>
        </div>
        <div>
          <label>
            Admin?
            <input type="checkbox" name="admin" />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
    </div>
  )
}

export default UserAdd
