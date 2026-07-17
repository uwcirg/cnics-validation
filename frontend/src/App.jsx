import { useEffect, useState } from 'react'
import { Route, BrowserRouter as Router, Routes } from 'react-router-dom'
import BaseLayout from './components/BaseLayout'
import { documentTitle, resolveStudyTitle } from './components/studyTitle'
import ProtectedRoute from './components/ProtectedRoute'
import Admin from './pages/Admin'
import CriteriaAdd from './pages/CriteriaAdd'
import CriteriaDelete from './pages/CriteriaDelete'
import EventAdd from './pages/EventAdd'
import EventAddMany from './pages/EventAddMany'
import EventAssignMany from './pages/EventAssignMany'
import EventAssignThird from './pages/EventAssignThird'
import EventDownload from './pages/EventDownload'
import EventEdit from './pages/EventEdit'
import EventExport from './pages/EventExport'
import EventIndex from './pages/EventIndex'
import EventReupload from './pages/EventReupload'
import EventReview from './pages/EventReview'
import EventScreen from './pages/EventScreen'
import EventScrub from './pages/EventScrub'
import EventSendMany from './pages/EventSendMany'
import EventUpload from './pages/EventUpload'
import EventViewAll from './pages/EventViewAll'
import Home from './pages/Home'
import SolicitationAdd from './pages/SolicitationAdd'
import SolicitationDelete from './pages/SolicitationDelete'
import UserAdd from './pages/UserAdd'
import UserDelete from './pages/UserDelete'
import UserEdit from './pages/UserEdit'
import UserLogout from './pages/UserLogout'
import UsersViewAll from './pages/UsersViewAll'
import VTEAdmin from './studies/vte/Admin'
import VTECriteriaAdd from './studies/vte/CriteriaAdd'
import VTECriteriaDelete from './studies/vte/CriteriaDelete'
import VTEEventAdd from './studies/vte/EventAdd'
import VTEEventAddMany from './studies/vte/EventAddMany'
import VTEEventAssignMany from './studies/vte/EventAssignMany'
import VTEEventAssignThird from './studies/vte/EventAssignThird'
import VTEEventDownload from './studies/vte/EventDownload'
import VTEEventEdit from './studies/vte/EventEdit'
import VTEEventExport from './studies/vte/EventExport'
import VTEEventReupload from './studies/vte/EventReupload'
import VTEEventReview from './studies/vte/EventReview'
import VTEEventScreen from './studies/vte/EventScreen'
import VTEEventScrub from './studies/vte/EventScrub'
import VTEEventSendMany from './studies/vte/EventSendMany'
import VTEEventUpload from './studies/vte/EventUpload'
import VTEEventViewAll from './studies/vte/EventViewAll'
import VTEHome from './studies/vte/Home'
import VTESolicitationAdd from './studies/vte/SolicitationAdd'
import VTESolicitationDelete from './studies/vte/SolicitationDelete'
import VTEUserAdd from './studies/vte/UserAdd'
import VTEUserDelete from './studies/vte/UserDelete'
import VTEUserEdit from './studies/vte/UserEdit'
import VTEUsersViewAll from './studies/vte/UsersViewAll'

function App() {
  const [auth, setAuth] = useState({})
  // Conservative full-workflow default (FR-004): used until GET /api/config
  // resolves and as the fallback if it cannot be fetched. The bypassed-stage
  // routes below are dropped only once the resolved config reports a stage
  // off, so hide/show decisions are driven by the controls, not a study
  // name (FR-021).
  const [workflow, setWorkflow] = useState({
    scrubbing: true,
    screening: true,
    sending: true,
    reviewer_count: 2,
  })
  // Study type from GET /api/config — the fallback source for the banner's
  // study title. Empty until the config resolves.
  const [studyType, setStudyType] = useState('')
  // Optional STUDY_TITLE override from GET /api/config — a free-form display
  // string (e.g. "DEXA Scans Validation") shown verbatim in the banner and
  // used to build the browser tab title. Empty when not configured.
  const [studyTitle, setStudyTitle] = useState('')
  // Whether GET /api/config has resolved. The home page gates its study-aware
  // review boxes on this so it never paints fallback content before the real
  // study type is known (spec 007, FR-010 — no content flash).
  const [configResolved, setConfigResolved] = useState(false)
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

  useEffect(() => {
    let cancelled = false
    async function fetchMe() {
      try {
        const res = await fetch(`${apiUrl}/api/auth/me`, { credentials: 'include' })
        if (!cancelled && res.ok) {
          const json = await res.json()
          setAuth(json.data || {})
        } else if (!cancelled) {
          setAuth({})
        }
      } catch {
        if (!cancelled) setAuth({})
      }
    }
    async function fetchConfig() {
      try {
        const res = await fetch(`${apiUrl}/api/config`, { credentials: 'include' })
        if (!cancelled && res.ok) {
          const json = await res.json()
          const wf = json && json.data && json.data.workflow
          if (wf) setWorkflow(wf)
          const st = json && json.data && json.data.study_type
          if (st) setStudyType(st)
          const stt = json && json.data && json.data.study_title
          if (stt) setStudyTitle(stt)
        }
      } catch {
        // Keep the conservative full-workflow default on any failure.
      } finally {
        // Mark config resolved regardless of outcome so the home page can
        // render its review boxes (falling back to mci content if the fetch
        // failed) rather than hiding them forever (FR-010, FR-008).
        if (!cancelled) setConfigResolved(true)
      }
    }
    fetchMe()
    fetchConfig()
    return () => {
      cancelled = true
    }
  }, [apiUrl])

  // The resolved banner study title — a STUDY_TITLE override, else derived
  // from the study type. Drives both the banner and the browser tab title.
  const bannerTitle = resolveStudyTitle(studyTitle, studyType)
  useEffect(() => {
    document.title = documentTitle(bannerTitle)
  }, [bannerTitle])

  return (
    <Router>
      <BaseLayout auth={auth} study_title={bannerTitle}>
        <Routes>
          {/* Public routes */}
          <Route path="/" element={<Home auth={auth} studyType={studyType} configResolved={configResolved} />} />
          <Route path="/vte" element={<VTEHome auth={auth} />} />
          <Route path="/events" element={<EventIndex />} />
          <Route path="/users/logout" element={<UserLogout />} />
          <Route path="/logout" element={<UserLogout />} />
          
          {/* Admin-only routes */}
          <Route path="/admin" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <Admin />
            </ProtectedRoute>
          } />
          <Route path="/vte/admin" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEAdmin />
            </ProtectedRoute>
          } />
          <Route path="/events/add" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventAdd />
            </ProtectedRoute>
          } />
          <Route path="/vte/add" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEEventAdd />
            </ProtectedRoute>
          } />
          <Route path="/events/addMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventAddMany />
            </ProtectedRoute>
          } />
          <Route path="/vte/addMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEEventAddMany />
            </ProtectedRoute>
          } />
          <Route path="/vte/assignMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEEventAssignMany />
            </ProtectedRoute>
          } />
          <Route path="/vte/assignThird" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEEventAssignThird />
            </ProtectedRoute>
          } />
          <Route path="/vte/sendMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEEventSendMany />
            </ProtectedRoute>
          } />
          <Route path="/events/assignMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventAssignMany workflow={workflow} />
            </ProtectedRoute>
          } />
          {/* Sending is a bypassable stage — the send route is registered
              only when the sending control is enabled (FR-018, FR-021). */}
          {workflow.sending && (
            <Route path="/events/sendMany" element={
              <ProtectedRoute requiredRoles={['admin']} auth={auth}>
                <EventSendMany />
              </ProtectedRoute>
            } />
          )}
          <Route path="/events/export" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventExport />
            </ProtectedRoute>
          } />
          <Route path="/vte/export" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEEventExport />
            </ProtectedRoute>
          } />
          <Route path="/vte/download" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTEEventDownload />
            </ProtectedRoute>
          } />
          <Route path="/users/viewAll" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <UsersViewAll />
            </ProtectedRoute>
          } />
          <Route path="/users/add" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <UserAdd auth={auth} />
            </ProtectedRoute>
          } />
          <Route path="/users/edit" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <UserEdit auth={auth} />
            </ProtectedRoute>
          } />
          <Route path="/users/delete" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <UserDelete />
            </ProtectedRoute>
          } />
          <Route path="/vte/users/viewAll" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEUsersViewAll />
            </ProtectedRoute>
          } />
          <Route path="/vte/users/add" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEUserAdd auth={auth} />
            </ProtectedRoute>
          } />
          <Route path="/vte/users/edit" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEUserEdit auth={auth} />
            </ProtectedRoute>
          } />
          <Route path="/vte/users/delete" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <VTEUserDelete />
            </ProtectedRoute>
          } />
          
          {/* Uploader routes (uploader or admin) */}
          <Route path="/events/upload" element={
            <ProtectedRoute requiredRoles={['uploader', 'admin']} auth={auth}>
              <EventUpload />
            </ProtectedRoute>
          } />
          <Route path="/vte/upload" element={
            <ProtectedRoute requiredRoles={['uploader', 'admin']} auth={auth}>
              <VTEEventUpload />
            </ProtectedRoute>
          } />
          <Route path="/events/reupload" element={
            <ProtectedRoute requiredRoles={['uploader', 'admin']} auth={auth}>
              <EventReupload />
            </ProtectedRoute>
          } />
          <Route path="/vte/reupload" element={
            <ProtectedRoute requiredRoles={['uploader', 'admin']} auth={auth}>
              <VTEEventReupload />
            </ProtectedRoute>
          } />
          
          {/* Reviewer routes (reviewer or admin) */}
          <Route path="/events/review" element={
            <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
              <EventReview />
            </ProtectedRoute>
          } />
          <Route path="/vte/review" element={
            <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
              <VTEEventReview />
            </ProtectedRoute>
          } />
          {/* Screening is a bypassable stage (FR-018, FR-021). */}
          {workflow.screening && (
            <Route path="/events/screen" element={
              <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
                <EventScreen />
              </ProtectedRoute>
            } />
          )}
          <Route path="/vte/screen" element={
            <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
              <VTEEventScreen />
            </ProtectedRoute>
          } />
          {/* Third-reviewer assignment exists only in a multi-reviewer
              configuration (FR-019, FR-021). */}
          {workflow.reviewer_count > 1 && (
            <Route path="/events/assignThird" element={
              <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
                <EventAssignThird />
              </ProtectedRoute>
            } />
          )}

          {/* Multi-role routes (reviewer, uploader, or admin) */}
          {/* Scrubbing is a bypassable stage (FR-018, FR-021). */}
          {workflow.scrubbing && (
            <Route path="/events/scrub" element={
              <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
                <EventScrub />
              </ProtectedRoute>
            } />
          )}
          <Route path="/vte/scrub" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTEEventScrub />
            </ProtectedRoute>
          } />
          <Route path="/events/edit" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <EventEdit />
            </ProtectedRoute>
          } />
          <Route path="/vte/edit" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTEEventEdit />
            </ProtectedRoute>
          } />
          <Route path="/events/viewAll" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <EventViewAll workflow={workflow} />
            </ProtectedRoute>
          } />
          <Route path="/vte/viewAll" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTEEventViewAll />
            </ProtectedRoute>
          } />
          <Route path="/events/download" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <EventDownload />
            </ProtectedRoute>
          } />
          <Route path="/solicitations/add" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <SolicitationAdd />
            </ProtectedRoute>
          } />
          <Route path="/solicitations/delete" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <SolicitationDelete />
            </ProtectedRoute>
          } />
          <Route path="/criteria/add" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <CriteriaAdd />
            </ProtectedRoute>
          } />
          <Route path="/criteria/delete" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <CriteriaDelete />
            </ProtectedRoute>
          } />
          <Route path="/vte/criteria/add" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTECriteriaAdd />
            </ProtectedRoute>
          } />
          <Route path="/vte/criteria/delete" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTECriteriaDelete />
            </ProtectedRoute>
          } />
          <Route path="/vte/solicitations/add" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTESolicitationAdd />
            </ProtectedRoute>
          } />
          <Route path="/vte/solicitations/delete" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <VTESolicitationDelete />
            </ProtectedRoute>
          } />
        </Routes>
      </BaseLayout>
    </Router>
  )
}

export default App
