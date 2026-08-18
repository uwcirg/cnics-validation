// Notifications for the whole application. There are 77 call sites across 30
// files, so the signature below is fixed: `showToast(message, type, timeoutMs)`
// keeps working unchanged everywhere. What changed is the behavior by type —
// `warning` and `error` now stay until dismissed and ignore `timeoutMs`, while
// `success` and `info` still auto-dismiss (research D15). That makes 59 of the
// 77 notifications click-to-dismiss, which is the intent, not a side effect:
// error detail used to evaporate before it could be read or copied.

const PERSISTENT_TYPES = ['warning', 'error']

// Persistent entries accumulate, so the number visible at once is capped and
// the oldest is evicted first — otherwise a page reporting errors in a loop
// grows a column of undismissable boxes off the top of the viewport.
const MAX_PERSISTENT_VISIBLE = 4

const BACKGROUNDS = {
  success: '#2e7d32',
  error: '#c62828',
  warning: '#ed6c02',
  info: '#1976d2',
}

function evictOldestPersistent(root) {
  const persistent = root.querySelectorAll('[data-toast-persistent="true"]')
  for (let i = 0; i <= persistent.length - 1 - MAX_PERSISTENT_VISIBLE; i += 1) {
    try { root.removeChild(persistent[i]) } catch { /* already gone */ }
  }
}

export function showToast(message, type = 'info', timeoutMs = 3000) {
  const root = document.getElementById('toast-root')
  if (!root) return alert(message)

  const persistent = PERSISTENT_TYPES.includes(type)

  const wrap = document.createElement('div')
  wrap.style.marginTop = '8px'
  if (persistent) wrap.dataset.toastPersistent = 'true'

  const el = document.createElement('div')
  el.style.display = 'flex'
  el.style.alignItems = 'flex-start'
  el.style.gap = '10px'
  el.style.padding = '10px 14px'
  el.style.borderRadius = '6px'
  el.style.color = '#fff'
  el.style.fontSize = '14px'
  el.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)'
  el.style.background = BACKGROUNDS[type] || BACKGROUNDS.info
  // Errors interrupt; everything else is announced politely. No `user-select`
  // is set anywhere here, so the text stays selectable and copyable.
  el.setAttribute('role', type === 'error' ? 'alert' : 'status')

  const text = document.createElement('span')
  text.textContent = message
  text.style.flex = '1'
  el.appendChild(text)

  const remove = () => {
    try { root.removeChild(wrap) } catch { /* already gone */ }
  }

  if (persistent) {
    // A real button, so it is reachable and operable from the keyboard. The
    // inline styles override the application's global button rule, which is a
    // solid blue fill that would be unreadable inside a colored toast.
    const dismiss = document.createElement('button')
    dismiss.type = 'button'
    dismiss.textContent = '×'
    dismiss.setAttribute('aria-label', 'Dismiss notification')
    dismiss.style.background = 'transparent'
    dismiss.style.border = '1px solid rgba(255,255,255,0.7)'
    dismiss.style.borderRadius = '4px'
    dismiss.style.color = '#fff'
    dismiss.style.cursor = 'pointer'
    dismiss.style.fontSize = '14px'
    dismiss.style.lineHeight = '1'
    dismiss.style.padding = '2px 7px'
    dismiss.addEventListener('click', remove)
    el.appendChild(dismiss)
  }

  wrap.appendChild(el)
  root.appendChild(wrap)

  if (persistent) evictOldestPersistent(root)
  else setTimeout(remove, timeoutMs)
}
