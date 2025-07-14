function SolicitationAdd() {
  return (
    <div>
      <h1>Add Solicitation</h1>
      <form>
        <div>
          <label>
            Event ID:
            <input type="text" name="event_id" />
          </label>
        </div>
        <div>
          <label>
            Date:
            <input type="date" name="date" />
          </label>
        </div>
        <div>
          <label>
            Contact:
            <input type="text" name="contact" />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
    </div>
  )
}

export default SolicitationAdd
