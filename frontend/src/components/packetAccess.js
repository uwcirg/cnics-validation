// Pre-flight check for GET /api/events/download/<id>.
//
// The server refuses a caller without access to an event's packet with 403
// (spec 013 D4). A plain download link would save that error body as a
// file, so download links check first with a HEAD request and show a
// message instead.

export const NO_ACCESS_MESSAGE = "You do not have access to this event's document."

/**
 * @param {string} url the packet's download URL
 * @returns {Promise<string|null>} null when the download may proceed,
 *   otherwise a message to show
 */
export async function checkPacketAccess(url) {
  try {
    const res = await fetch(url, { method: 'HEAD', credentials: 'include' })
    if (res.ok) return null
    if (res.status === 403) return NO_ACCESS_MESSAGE
    if (res.status === 404) return 'No document is available for this event yet.'
    return 'The document could not be downloaded.'
  } catch {
    return 'Network or server error while downloading.'
  }
}
