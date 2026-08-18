import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'

// Presentation over the outcome useCsvImport produces. The rules it follows
// are the "Required output per outcome" table in
// specs/009-save-import-csv/contracts/import-feedback-ui.md, and the one that
// is a contract rather than a preference: the `undetermined` and `network`
// branches assert nothing about what the import did, because the client does
// not know. Saying otherwise is what caused administrators to re-upload files
// and create duplicate events.

const panelStyle = {
  border: '1px solid #90a4b8',
  borderLeftWidth: '6px',
  borderRadius: '6px',
  padding: '12px 16px',
  margin: '16px 0',
  background: '#f6f8fa',
  textAlign: 'left',
}

const OUTCOME_ACCENT = {
  imported: '#2e7d32',
  partial: '#ed6c02',
  nothing: '#c62828',
  refused: '#c62828',
  undetermined: '#1565c0',
  network: '#1565c0',
}

const rowsRegionStyle = {
  maxHeight: '18em',
  overflowY: 'auto',
  border: '1px solid #cfd8dc',
  borderRadius: '4px',
  background: '#ffffff',
  padding: '8px 8px 8px 28px',
  margin: '8px 0',
}

const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`

// The record for this submission, or the undifferentiated list when the client
// never received an id — which is always the `network` case and sometimes the
// `undetermined` one.
const recordHref = (importId) =>
  importId ? `/events/imports?import_id=${encodeURIComponent(importId)}` : '/events/imports'

function RecordLink({ importId, children }) {
  return <Link to={recordHref(importId)}>{children}</Link>
}

function ImportResult({ result, onDismiss }) {
  const [copyState, setCopyState] = useState('idle')
  const rowsRef = useRef(null)

  if (!result) return null

  const { outcome, importedCount, skippedCount, errors = [], importId, reason } = result

  const selectRows = () => {
    const node = rowsRef.current
    if (!node || typeof window.getSelection !== 'function') return false
    const selection = window.getSelection()
    const range = document.createRange()
    range.selectNodeContents(node)
    selection.removeAllRanges()
    selection.addRange(range)
    return true
  }

  // Clipboard first; leaving the text selected is the fallback when the API is
  // unavailable (research D16). Nothing here logs or stores the text — the
  // skipped-row messages carry site patient identifiers.
  const handleCopy = async () => {
    const text = errors.join('\n')
    try {
      if (!navigator.clipboard || !navigator.clipboard.writeText) throw new Error('unavailable')
      await navigator.clipboard.writeText(text)
      setCopyState('copied')
    } catch {
      setCopyState(selectRows() ? 'selected' : 'idle')
    }
  }

  // One line per skipped row in a scrollable, selectable region — never joined
  // into a single string, which is what made a few hundred skipped rows
  // unreadable in the reported screenshot.
  const skippedRows = errors.length > 0 && (
    <div>
      <h3>Rows that were skipped</h3>
      <ul ref={rowsRef} style={rowsRegionStyle}>
        {errors.map((message, i) => (
          <li key={i}>{message}</li>
        ))}
      </ul>
      <p>
        <button type="button" onClick={handleCopy}>
          Copy all skipped rows
        </button>{' '}
        {copyState === 'copied' && <span>Copied to the clipboard.</span>}
        {copyState === 'selected' && (
          <span>Selected above &mdash; copy with Ctrl+C.</span>
        )}
      </p>
    </div>
  )

  let heading
  let body

  if (outcome === 'imported') {
    heading = `Imported ${plural(importedCount, 'event')}.`
    body = (
      <p>
        Every row was imported. <RecordLink importId={importId}>View this import</RecordLink>.
      </p>
    )
  } else if (outcome === 'partial') {
    heading = `Imported ${plural(importedCount, 'event')}, and skipped ${plural(skippedCount, 'row')}.`
    body = (
      <>
        <p>
          The events for the imported rows were created. The skipped rows were not.{' '}
          <RecordLink importId={importId}>View this import</RecordLink>.
        </p>
        {skippedRows}
      </>
    )
  } else if (outcome === 'nothing') {
    heading = 'No events were created.'
    body = (
      <>
        {reason && <p>{reason}</p>}
        <p>
          The file was kept even though nothing was imported.{' '}
          <RecordLink importId={importId}>View this import</RecordLink>.
        </p>
        {skippedRows}
      </>
    )
  } else if (outcome === 'refused') {
    heading = 'The submission was not accepted.'
    body = (
      <>
        {reason && <p>{reason}</p>}
        <p>
          The attempt was recorded, but the file itself was not kept.{' '}
          <RecordLink importId={importId}>View this import</RecordLink>.
        </p>
      </>
    )
  } else if (outcome === 'undetermined') {
    // MUST NOT assert failure, and MUST NOT show a count. The reply could not
    // be read; the import may well have completed in full.
    heading = 'The outcome of this import is not known.'
    body = (
      <p>
        The server&rsquo;s reply could not be read, so this page cannot say what the
        import did. It may have completed. Every submission is recorded, so the
        import history has the answer:{' '}
        <RecordLink importId={importId}>view the import record</RecordLink>. Check
        there before submitting the file again &mdash; submitting it twice would
        create the events twice.
      </p>
    )
  } else {
    // network — the same rule, for a request that never came back at all.
    heading = 'The request did not complete.'
    body = (
      <p>
        The browser did not receive a reply, so this page cannot say whether the
        server received the file or what it did with it. Every submission the
        server processes is recorded:{' '}
        <RecordLink importId={importId}>check the import history</RecordLink>{' '}
        before submitting the file again.
      </p>
    )
  }

  return (
    <section
      style={{ ...panelStyle, borderLeftColor: OUTCOME_ACCENT[outcome] || '#90a4b8' }}
      aria-live="polite"
    >
      <h2>{heading}</h2>
      {body}
      <p>
        <button type="button" onClick={onDismiss}>
          Dismiss this result
        </button>
      </p>
    </section>
  )
}

export default ImportResult
