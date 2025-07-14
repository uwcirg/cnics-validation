function EventAddMany() {
  return (
    <div>
      <h1>Add Multiple Events</h1>
      <form>
        <div>
          <label>
            CSV File:
            <input type="file" name="events_csv" />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
    </div>
  )
}

export default EventAddMany
