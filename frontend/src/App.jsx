import { useEffect, useState } from 'react'
import { Route, BrowserRouter as Router, Routes } from 'react-router-dom'
import BaseLayout from './components/BaseLayout'
import DevAuthTester from './components/DevAuthTester'
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
import VTEEventReview from './studies/vte/EventReview'
import VTEEventScreen from './studies/vte/EventScreen'
import VTEEventScrub from './studies/vte/EventScrub'
import VTEEventUpload from './studies/vte/EventUpload'

function App() {
  const [auth, setAuth] = useState({})
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
    fetchMe()
    return () => {
      cancelled = true
    }
  }, [apiUrl])

  return (
    <Router>
      <DevAuthTester currentAuth={auth} onAuthChange={setAuth} />
      <BaseLayout auth={auth}>
        <Routes>
          {/* Public routes */}
          <Route path="/" element={<Home auth={auth} />} />
          <Route path="/events" element={<EventIndex />} />
          <Route path="/users/logout" element={<UserLogout />} />
          <Route path="/logout" element={<UserLogout />} />
          
          {/* Admin-only routes */}
          <Route path="/admin" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <Admin />
            </ProtectedRoute>
          } />
          <Route path="/events/add" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventAdd />
            </ProtectedRoute>
          } />
          <Route path="/events/addMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventAddMany />
            </ProtectedRoute>
          } />
          <Route path="/events/assignMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventAssignMany />
            </ProtectedRoute>
          } />
          <Route path="/events/sendMany" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventSendMany />
            </ProtectedRoute>
          } />
          <Route path="/events/export" element={
            <ProtectedRoute requiredRoles={['admin']} auth={auth}>
              <EventExport />
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
          <Route path="/events/screen" element={
            <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
              <EventScreen />
            </ProtectedRoute>
          } />
          <Route path="/vte/screen" element={
            <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
              <VTEEventScreen />
            </ProtectedRoute>
          } />
          <Route path="/events/assignThird" element={
            <ProtectedRoute requiredRoles={['reviewer', 'admin']} auth={auth}>
              <EventAssignThird />
            </ProtectedRoute>
          } />
          
          {/* Multi-role routes (reviewer, uploader, or admin) */}
          <Route path="/events/scrub" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <EventScrub />
            </ProtectedRoute>
          } />
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
          <Route path="/events/viewAll" element={
            <ProtectedRoute requiredRoles={['reviewer', 'uploader', 'admin']} auth={auth}>
              <EventViewAll />
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
        </Routes>
      </BaseLayout>
    </Router>
  )
}

export default App
