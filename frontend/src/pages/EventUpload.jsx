function EventUpload() {
  return (
    <div>
      <h1>Upload Event Packet</h1>
      <form>
        <div>
          <label>
            Event Packet File:
            <input type="file" name="packet" />
          </label>
        </div>
        <button type="submit">Upload</button>
      </form>
    </div>
  )
}

export default EventUpload
