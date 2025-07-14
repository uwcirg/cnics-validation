function EventAdd() {
  return (
    <div>
      <h1>Add Event</h1>
      <form>
        <div>
          <label>
            Site Patient Id:
            <input type="text" name="site_patient_id" />
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
            Event Date:
            <input type="date" name="event_date" />
          </label>
        </div>
        <div>
          <label>
            Criterion Name:
            <input type="text" name="criterion_name" />
          </label>
        </div>
        <div>
          <label>
            Criterion Value:
            <input type="text" name="criterion_value" />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
    </div>
  )
}

export default EventAdd
