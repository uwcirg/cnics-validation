import { BrowserRouter as Router, Route, Routes } from 'react-router-dom'
import BaseLayout from './components/BaseLayout'
import Home from './pages/Home'
import EventIndex from './pages/EventIndex'
import EventAdd from './pages/EventAdd'
import EventAddMany from './pages/EventAddMany'
import EventAssignMany from './pages/EventAssignMany'
import EventUpload from './pages/EventUpload'
import EventReview from './pages/EventReview'
import EventScreen from './pages/EventScreen'
import EventScrub from './pages/EventScrub'
import EventViewAll from './pages/EventViewAll'
import UsersViewAll from './pages/UsersViewAll'
import UserAdd from './pages/UserAdd'
import UserEdit from './pages/UserEdit'
import UserDelete from './pages/UserDelete'
import UserLogout from './pages/UserLogout'
import SolicitationAdd from './pages/SolicitationAdd'
import SolicitationDelete from './pages/SolicitationDelete'
import CriteriaAdd from './pages/CriteriaAdd'
import CriteriaDelete from './pages/CriteriaDelete'
import ErrorNotAuthorized from './pages/ErrorNotAuthorized'
import ErrorUnknownUser from './pages/ErrorUnknownUser'

function App() {
  const auth = { admin: true, uploader: true, reviewer: true, username: 'demo' }

  return (
    <Router>
      <BaseLayout auth={auth}>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/events" element={<EventIndex />} />
          <Route path="/events/add" element={<EventAdd />} />
          <Route path="/events/addMany" element={<EventAddMany />} />
          <Route path="/events/assignMany" element={<EventAssignMany />} />
          <Route path="/events/upload" element={<EventUpload />} />
          <Route path="/events/review" element={<EventReview />} />
          <Route path="/events/screen" element={<EventScreen />} />
          <Route path="/events/scrub" element={<EventScrub />} />
          <Route path="/events/viewAll" element={<EventViewAll />} />
          <Route path="/users/viewAll" element={<UsersViewAll />} />
          <Route path="/users/add" element={<UserAdd />} />
          <Route path="/users/edit" element={<UserEdit />} />
          <Route path="/users/delete" element={<UserDelete />} />
          <Route path="/users/logout" element={<UserLogout />} />
          <Route path="/logout" element={<UserLogout />} />
          <Route path="/solicitations/add" element={<SolicitationAdd />} />
          <Route path="/solicitations/delete" element={<SolicitationDelete />} />
          <Route path="/criteria/add" element={<CriteriaAdd />} />
          <Route path="/criteria/delete" element={<CriteriaDelete />} />
          <Route path="/error/notAuthorized" element={<ErrorNotAuthorized authUsername={auth.username} controller="" action="" />} />
          <Route path="/error/unknownUser" element={<ErrorUnknownUser authUsername={auth.username} />} />
        </Routes>
      </BaseLayout>
    </Router>
  )
}

export default App
