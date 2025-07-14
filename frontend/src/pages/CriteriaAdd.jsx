function CriteriaAdd() {
  return (
    <div>
      <h1>Add Criterion</h1>
      <form>
        <div>
          <label>
            Event ID:
            <input type="text" name="event_id" />
          </label>
        </div>
        <div>
          <label>
            Name:
            <input type="text" name="name" />
          </label>
        </div>
        <div>
          <label>
            Value:
            <input type="text" name="value" />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
    </div>
  )
}

export default CriteriaAdd
