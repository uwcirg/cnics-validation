export function showToast(message, type = 'info', timeoutMs = 3000) {
  const root = document.getElementById('toast-root')
  if (!root) return alert(message)
  const wrap = document.createElement('div')
  wrap.style.marginTop = '8px'
  const el = document.createElement('div')
  el.textContent = message
  el.style.padding = '10px 14px'
  el.style.borderRadius = '6px'
  el.style.color = '#fff'
  el.style.fontSize = '14px'
  el.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)'
  el.style.background = type === 'success' ? '#2e7d32' : type === 'error' ? '#c62828' : type === 'warning' ? '#ed6c02' : '#1976d2'
  wrap.appendChild(el)
  root.appendChild(wrap)
  setTimeout(() => {
    try { root.removeChild(wrap) } catch {}
  }, timeoutMs)
}


