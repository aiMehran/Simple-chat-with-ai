import React from 'react'
import { Routes, Route, Link } from 'react-router-dom'

function Header() {
  // Placeholder; will fetch /users/me after login
  return (
    <header className="app-header">
      <nav className="nav">
        <Link to="/projects">Projects</Link>
        <Link to="/moodboard">Moodboard</Link>
        <Link to="/whiteboard">Whiteboard</Link>
        <Link to="/calendar">Calendar</Link>
        <Link to="/workflow">Workflow</Link>
        <Link to="/activities">Activities</Link>
        <Link to="/settings">Settings</Link>
      </nav>
      <div className="user">Signed in: <strong>Guest</strong></div>
    </header>
  )
}

function Page({ title }: { title: string }) {
  return <div className="page"><h2>{title}</h2><p>Coming soon…</p></div>
}

export default function App() {
  return (
    <div className="app">
      <Header />
      <main className="content">
        <Routes>
          <Route path="/" element={<Page title="Home" />} />
          <Route path="/projects" element={<Page title="Projects" />} />
          <Route path="/moodboard" element={<Page title="Moodboard" />} />
          <Route path="/whiteboard" element={<Page title="Whiteboard" />} />
          <Route path="/calendar" element={<Page title="Calendar" />} />
          <Route path="/workflow" element={<Page title="Workflow" />} />
          <Route path="/activities" element={<Page title="Activities" />} />
          <Route path="/settings" element={<Page title="Settings" />} />
        </Routes>
      </main>
    </div>
  )
}