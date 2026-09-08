// Base URL for the backend API. When running under Docker Compose the
// environment variable is provided by the compose file. Fallback to a
// relative path so the frontend can be served without configuration.
// Matches the definition in the pages that render this component.
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

// Render one guidance box's optional file links. Returns null when the box
// defines no links, so no orphaned "Full instructions:" / "View as:" label is
// shown (spec 007, FR-004). A `.doc`-style link downloads; a `.pdf`-style link
// opens in a new tab — matching the original MI behavior.
//
// Moved here from Home.jsx (spec 010) so the scrubbing and review pages render
// their document links from the same per-study source as the home page rather
// than hard-coding one study's filenames (FR-014). Its existing empty-list
// behaviour is exactly what FR-015 and FR-016 require of a study that has no
// documents, so the empty case needed no new code.
function GuidanceLinks({ box }) {
  if (!box.links || box.links.length === 0) return null
  return (
    <div>
      {box.linkLabel ? `${box.linkLabel} ` : ''}
      {box.links.map((link, i) => (
        <span key={link.href}>
          {i > 0 ? ' | ' : ''}
          {link.download ? (
            <a href={`${API_BASE}${link.href}`} download>{link.label}</a>
          ) : (
            <a href={`${API_BASE}${link.href}`} target="_blank">{link.label}</a>
          )}
        </span>
      ))}
    </div>
  )
}

export default GuidanceLinks
