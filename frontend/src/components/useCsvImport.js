import { useCallback, useEffect, useRef, useState } from 'react'

// Same-origin by default, which is what frontend/default.env recommends and
// what the deployment uses.
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

// Sentinel distinguishing "the body would not parse" from "the body parsed to
// null", which are different pieces of evidence.
const UNPARSEABLE = Symbol('unparseable')

/**
 * A body is only worth parsing as JSON if the server said it is JSON. Testing
 * the header *before* parsing is what turns the observed failure — an HTML SPA
 * shell answered by the wrong server (research D10) — into evidence rather
 * than an incidental parse exception.
 */
export function isJsonResponse(res) {
  const header = (res.headers && res.headers.get && res.headers.get('content-type')) || ''
  return header.split(';')[0].trim().toLowerCase().endsWith('json')
}

async function readJson(res) {
  try {
    return await res.json()
  } catch {
    return UNPARSEABLE
  }
}

const undetermined = (importId = null) => ({
  outcome: 'undetermined',
  importedCount: null,
  skippedCount: null,
  errors: [],
  importId,
  reason: null,
})

const network = () => ({
  outcome: 'network',
  importedCount: null,
  skippedCount: null,
  errors: [],
  importId: null,
  reason: null,
})

/**
 * The ordered classification from contracts/import-feedback-ui.md. Order is
 * normative, not stylistic — see the comments on each step. `fetch` throwing
 * is step 1 and is handled by the caller, which is the only place that can
 * observe it.
 */
export async function classifyResponse(res) {
  // 2. Oversize first. Its body *is* JSON, so testing it after the JSON checks
  //    would work, but testing it first is what guarantees the server's
  //    specific message about the size limit survives (009 FR-006).
  if (res.status === 413) {
    const body = await readJson(res)
    const parsed = body === UNPARSEABLE ? null : body
    return {
      outcome: 'refused',
      importedCount: null,
      skippedCount: null,
      errors: [],
      importId: parsed?.data?.import_id ?? null,
      reason: parsed?.error ?? null,
    }
  }

  // 3. The server did not claim to be sending JSON. The client has no evidence
  //    about what the import did — which is not the same as evidence that it
  //    did nothing.
  if (!isJsonResponse(res)) return undetermined()

  // 4. It claimed JSON and was not.
  const body = await readJson(res)
  if (body === UNPARSEABLE) return undetermined()

  const data = (body && body.data) || {}
  const errors = Array.isArray(data.errors) ? data.errors : []
  const importId = data.import_id ?? null
  const reason = (body && body.error) ?? null
  // Read only after the body is known to be JSON, and never defaulted. The
  // defaulting of a missing `imported` to 0, and the reading of that 0 as
  // failure, is precisely the bug this module exists to remove.
  const imported = typeof data.imported === 'number' ? data.imported : null

  // 5. Rows were created.
  if (imported !== null && imported > 0) {
    return {
      outcome: errors.length ? 'partial' : 'imported',
      importedCount: imported,
      skippedCount: errors.length,
      errors,
      importId,
      reason,
    }
  }

  // 6. Otherwise nothing was created — but only where the server actually said
  //    so, by reporting a count of zero or by giving a reason. A JSON body
  //    carrying neither is off-contract and tells the client nothing, so it is
  //    reported as unknown rather than as a zero-event import.
  if (imported === null && !reason) return undetermined(importId)

  return {
    outcome: 'nothing',
    importedCount: imported ?? 0,
    skippedCount: errors.length,
    errors,
    importId,
    reason,
  }
}

/**
 * The bulk-import submission state machine (data-model.md § Submission state).
 * `phase === 'submitting'` is the single source for both the spinner and the
 * submit button's disabled attribute, so FR-013 and FR-014 cannot drift apart.
 */
export function useCsvImport() {
  const [phase, setPhase] = useState('idle')
  const [elapsedSeconds, setElapsedSeconds] = useState(0)
  const [result, setResult] = useState(null)
  const timerRef = useRef(null)

  const stopTimer = useCallback(() => {
    if (timerRef.current !== null) {
      clearInterval(timerRef.current)
      timerRef.current = null
    }
  }, [])

  // The interval must not outlive the request, and must not outlive the page
  // either.
  useEffect(() => stopTimer, [stopTimer])

  const submit = useCallback(async (file) => {
    if (!file) return null
    const form = new FormData()
    form.append('events_csv', file)
    const startedAt = Date.now()
    setResult(null)
    setElapsedSeconds(0)
    setPhase('submitting')
    stopTimer()
    timerRef.current = setInterval(() => {
      setElapsedSeconds(Math.floor((Date.now() - startedAt) / 1000))
    }, 1000)

    let outcome
    try {
      const res = await fetch(`${API_BASE}/api/events/bulk`, {
        method: 'POST',
        credentials: 'include',
        body: form,
      })
      outcome = await classifyResponse(res)
    } catch {
      // 1. `fetch` itself threw: the request did not complete. Whether the
      //    server saw it is unknowable from here.
      outcome = network()
    } finally {
      stopTimer()
      setPhase('resolved')
    }
    setResult(outcome)
    return outcome
  }, [stopTimer])

  const dismiss = useCallback(() => {
    setResult(null)
    setElapsedSeconds(0)
    setPhase('idle')
  }, [])

  return { phase, elapsedSeconds, result, submit, dismiss }
}

export default useCsvImport
