# Cyrus Ultimate — Product & Technical Blueprint (WordPress Plugin)

This is the end-to-end blueprint for building Cyrus Ultimate as a WordPress plugin: a modern project management platform with moodboards, whiteboards, calendar, workflows, activities, notifications, @mentions, JWT auth, invitations/activation codes, CMS-backed themes (dark/light + color system), and a mobile-responsive glasmorphic UI.

## Final Vision (What success looks like)

- Modern SPA embedded in WordPress (shortcode/block) with smooth navigation between tabs: Projects, Moodboard, Whiteboard, Calendar, Workflow, Activities, Settings.
- Project cards with status timeline (dots with hover), editable timeline entries (backdated), images/notes, fullscreen sliders, and focus mode (header hides and content shifts up, with a back button).
- Moodboard with Pinterest-like masonry, auto-detect/import images from project, manual add, likes, comments, delete, per-project sync toggle.
- Whiteboards (à la Miro/Filestage) with groups, pin comments on images, and two-way sync with project media.
- Calendar aggregating all projects and major milestones/statuses; click-through to project detail/moodboard/whiteboard.
- Workflow builder: stages, tasks, assignees, mentions, comments, edits/deletes everywhere.
- Activity feed (daily/weekly/monthly) and notification center; @mentions trigger notifications.
- Auth with JWT (login), SMTP mail (invitations + activation codes), self-signup gated by admin-generated activation codes.
- Theme settings in WP admin: color system (primary/semantic palette), dark/light toggle; show current user’s name in the app header.
- Global and scoped search; first-class mobile responsiveness; accessible UI.

---

## Architecture Overview

- WordPress plugin (PHP) provides:
  - Custom REST API (`/wp-json/cyrus/v1/...`)
  - Custom database tables for performance + relational clarity
  - Settings pages (colors, SMTP settings reference, toggles)
  - Capability-based authorization for Admin vs Team Member
  - Email sending via `wp_mail` (wired to your SMTP plugin)
  - JWT issuance/validation (server-side) + refresh token rotation
  - Data sync for moodboard/whiteboard ↔ projects
  - Activity + Notification subsystem

- Frontend SPA (React + TypeScript):
  - Bundled with Vite, enqueued by plugin on the designated page
  - TanStack Query for data-fetching/caching; React Router for routing
  - Tailwind CSS with CSS variables for color system + dark mode
  - Headless UI/Radix UI + Reach UI for accessibility
  - Optional real-time via Pusher/Ably; fallback to short polling/SSE
  - Swiper for sliders; React Photo View/Lightbox; Masonry layout
  - react-konva for whiteboard pinning/annotations

- Data storage strategy:
  - WP attachments for images; custom mapping tables for project/moodboard/whiteboard relationships
  - Custom tables for: projects, statuses (timeline events), notes, workflows/stages/tasks, comments, activities, notifications, invitations/activation codes, memberships, whiteboards, pins, likes
  - Full-text indexes for search across titles/notes/comments

---

## Roles, Permissions, and Auth

- Roles:
  - `cyrus_admin` (capabilities: manage all plugin data, invite/generate activation codes, settings)
  - `cyrus_member` (capabilities: view/edit assigned projects, comment, upload within permissions)
  - Map to WP capabilities so WP admins can grant them

- JWT Authentication:
  - Endpoint: `POST /cyrus/v1/auth/login` (username/email + password → access token [short-lived] + refresh token [httpOnly if embedded, or secure storage])
  - Endpoint: `POST /cyrus/v1/auth/refresh`
  - Token claims: `sub` (user_id), `iss` (site_url), `iat`, `exp`, `scope` (role/caps), `jti`
  - Library: `firebase/php-jwt` bundled via Composer
  - Nonce/capability checks still applied per endpoint to prevent privilege escalation
  - Support WordPress cookie auth as fallback inside WP admin

- Invitations & Activation Codes:
  - Admin (or `cyrus_admin`) generates single-use activation codes, expirable, optionally bound to email.
  - Email invite with CTA to signup screen containing the code.
  - Signup Flow: `POST /cyrus/v1/auth/signup` with `code`, `email`, `password`, `first_name`, `last_name`, `phone`.
  - On success, user is created in WP with role `cyrus_member` (or configurable), linked to inviting admin’s organization/team.

---

## Data Model (Custom Tables)

Use `$wpdb->prefix` (e.g., `wp_`) + `cyrus_` prefix. All tables include: `id BIGINT PK`, `created_at`, `updated_at`, and foreign keys by ID (no MySQL FK constraints if you prefer WP portability, but index them).

Key enumerations:
- ProjectStatus: `not_started`, `preview_rendering`, `preview_feedback`, `render`, `feedback`, `compositing`, `renderfarm`, `delivery` (allow admin to extend via settings if needed)
- ActivityType: `project_created`, `status_changed`, `image_added`, `note_added`, `task_created`, `comment_added`, `mention`, `invite_sent`, `user_joined`, etc.

Suggested core tables (excerpt; add indices and constraints):

```sql
-- Projects
CREATE TABLE {prefix}cyrus_projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  current_status VARCHAR(64) NOT NULL DEFAULT 'not_started',
  due_date DATETIME NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (owner_user_id),
  INDEX (current_status),
  INDEX (due_date)
);

-- Memberships (which users belong to which projects + role)
CREATE TABLE {prefix}cyrus_project_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(64) NOT NULL DEFAULT 'member',
  created_at DATETIME NOT NULL,
  UNIQUE KEY (project_id, user_id),
  INDEX (user_id)
);

-- Timeline Events (status history)
CREATE TABLE {prefix}cyrus_status_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(64) NOT NULL,
  occurred_at DATETIME NOT NULL,
  added_by_user_id BIGINT UNSIGNED NOT NULL,
  note TEXT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'manual', -- manual|auto
  created_at DATETIME NOT NULL,
  INDEX (project_id, occurred_at),
  INDEX (status)
);

-- Notes (project-level)
CREATE TABLE {prefix}cyrus_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  body LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FULLTEXT KEY ft_body (body)
);

-- Media (mapping to WP attachments)
CREATE TABLE {prefix}cyrus_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attachment_id BIGINT UNSIGNED NOT NULL, -- wp_posts.ID for attachment
  project_id BIGINT UNSIGNED NOT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'project', -- project|moodboard|whiteboard
  created_at DATETIME NOT NULL,
  UNIQUE KEY (attachment_id, project_id),
  INDEX (project_id)
);

-- Moodboards (per project; can be implicit)
CREATE TABLE {prefix}cyrus_moodboards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  auto_sync TINYINT(1) NOT NULL DEFAULT 1, -- if on, new project media appear here
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY (project_id)
);

-- Moodboard Items (images)
CREATE TABLE {prefix}cyrus_moodboard_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  moodboard_id BIGINT UNSIGNED NOT NULL,
  attachment_id BIGINT UNSIGNED NOT NULL,
  added_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY (moodboard_id, attachment_id)
);

-- Likes + Comments on Moodboard
CREATE TABLE {prefix}cyrus_likes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(32) NOT NULL, -- moodboard_item|whiteboard_pin|note|task|comment
  scope_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY (scope, scope_id, user_id)
);

CREATE TABLE {prefix}cyrus_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(32) NOT NULL, -- moodboard_item|whiteboard_pin|project|task|note
  scope_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  body LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FULLTEXT KEY ft_body (body)
);

-- Whiteboards
CREATE TABLE {prefix}cyrus_whiteboards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (project_id)
);

-- Whiteboard Groups (image collections)
CREATE TABLE {prefix}cyrus_whiteboard_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  whiteboard_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX (whiteboard_id)
);

-- Whiteboard Items (images on board groups)
CREATE TABLE {prefix}cyrus_whiteboard_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  attachment_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY (group_id, attachment_id)
);

-- Pins (annotations on an image with coordinates)
CREATE TABLE {prefix}cyrus_whiteboard_pins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL, -- whiteboard_item
  x FLOAT NOT NULL,
  y FLOAT NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  text TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (item_id)
);

-- Workflow
CREATE TABLE {prefix}cyrus_workflows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY (project_id)
);

CREATE TABLE {prefix}cyrus_workflow_stages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workflow_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  position INT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (workflow_id)
);

CREATE TABLE {prefix}cyrus_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  stage_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  due_date DATETIME NULL,
  status VARCHAR(64) NOT NULL DEFAULT 'todo', -- todo|in_progress|done
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (project_id),
  INDEX (stage_id),
  INDEX (due_date)
);

CREATE TABLE {prefix}cyrus_task_assignees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY (task_id, user_id)
);

-- Activities & Notifications
CREATE TABLE {prefix}cyrus_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(64) NOT NULL,
  payload JSON NULL,
  occurred_at DATETIME NOT NULL,
  INDEX (project_id, occurred_at),
  INDEX (actor_user_id)
);

CREATE TABLE {prefix}cyrus_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(64) NOT NULL,
  payload JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX (user_id, is_read)
);

-- Mentions
CREATE TABLE {prefix}cyrus_mentions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(32) NOT NULL, -- note|comment|task|pin
  scope_id BIGINT UNSIGNED NOT NULL,
  mentioned_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX (mentioned_user_id)
);

-- Invitations & Activation Codes
CREATE TABLE {prefix}cyrus_activation_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_hash VARCHAR(255) NOT NULL,
  invited_email VARCHAR(255) NULL,
  invited_by_user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NULL,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  used_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY (code_hash)
);
```

Notes:
- Use hashed activation codes at rest (e.g., `password_hash`).
- Prefer JSON columns where supported; otherwise store as TEXT and JSON-encode.
- Add appropriate indexes for frequent queries (status filters, recent activities, full-text on notes/comments).

---

## API Design (REST Endpoints)

Base: `/wp-json/cyrus/v1`

- Auth
  - `POST /auth/login` → { access_token, refresh_token, user }
  - `POST /auth/refresh` → { access_token }
  - `POST /auth/signup` → { user } (requires `code`)

- Users
  - `GET /users/me`
  - `GET /users/search?q=` (for @mention autocomplete)

- Invitations/Activation Codes (cyrus_admin only)
  - `POST /activation-codes` → { code_last4, expires_at } (email optional)
  - `POST /activation-codes/send` → { sent: true } (sends via `wp_mail`)
  - `GET /activation-codes` (list, masked)

- Projects
  - `GET /projects` (filters: status, q, date range, member)
  - `POST /projects`
  - `GET /projects/{id}`
  - `PUT /projects/{id}`
  - `DELETE /projects/{id}`
  - `GET /projects/{id}/timeline` (status events)
  - `POST /projects/{id}/timeline` (add/edit backdated events)
  - `GET /projects/{id}/notes`
  - `POST /projects/{id}/notes`
  - `PUT /notes/{id}` / `DELETE /notes/{id}`
  - `GET /projects/{id}/media` (WP attachments mapped)
  - `POST /projects/{id}/media` (attach existing attachment_id or upload → WP media + map)

- Moodboard
  - `GET /projects/{id}/moodboard`
  - `PUT /projects/{id}/moodboard` (toggle auto_sync)
  - `POST /projects/{id}/moodboard/import` (scan project images → add to moodboard)
  - `POST /moodboard/items` (add attachment)
  - `DELETE /moodboard/items/{id}`
  - `POST /likes` / `DELETE /likes/{id}`
  - `POST /comments` / `PUT /comments/{id}` / `DELETE /comments/{id}`

- Whiteboard
  - `GET /projects/{id}/whiteboards`
  - `POST /projects/{id}/whiteboards`
  - `POST /whiteboards/{id}/groups`
  - `POST /whiteboards/groups/{groupId}/items` (add attachment)
  - `POST /whiteboards/items/{itemId}/pins`
  - `PUT /whiteboards/pins/{pinId}` / `DELETE /whiteboards/pins/{pinId}`
  - Two-way media sync: adding items here also maps into `cyrus_media` for the project

- Workflow & Tasks
  - `GET /projects/{id}/workflow`
  - `POST /projects/{id}/workflow` (create/replace)
  - `POST /workflow/{workflowId}/stages`
  - `PUT /workflow/stages/{id}` / `DELETE /workflow/stages/{id}`
  - `POST /projects/{id}/tasks`
  - `PUT /tasks/{id}` / `DELETE /tasks/{id}`
  - `POST /tasks/{id}/assignees` / `DELETE /tasks/{id}/assignees/{userId}`
  - `GET /projects/{id}/tasks` (filters: stage, status, assignee, q)

- Calendar & Search
  - `GET /calendar` (events aggregated from projects/tasks/status milestones)
  - `GET /search` (global) with `q`, optional `scope` (projects|notes|comments|tasks)

- Activities & Notifications
  - `GET /activities?range=day|week|month&projectId=`
  - `GET /notifications` (unread/read)
  - `POST /notifications/{id}/read`

All endpoints:
- Require JWT unless explicitly public.
- Enforce capabilities (e.g., only project members can mutate project data).
- Sanitize/validate all inputs; rate-limit sensitive endpoints.

---

## WordPress Plugin Structure

```
cyrus-ultimate/
├─ cyrus-ultimate.php                  # Plugin bootstrap
├─ composer.json                       # php-jwt, symfony/polyfill, etc.
├─ vendor/                             # Composer vendor (committed or built)
├─ includes/
│  ├─ Autoloader.php
│  ├─ Plugin.php
│  ├─ DB/
│  │  ├─ Installer.php                 # create/update tables
│  │  └─ Migrations/*
│  ├─ REST/
│  │  ├─ AuthController.php
│  │  ├─ ProjectsController.php
│  │  ├─ MoodboardController.php
│  │  ├─ WhiteboardController.php
│  │  ├─ WorkflowController.php
│  │  ├─ CalendarController.php
│  │  ├─ ActivitiesController.php
│  │  └─ NotificationsController.php
│  ├─ Services/
│  │  ├─ JwtService.php
│  │  ├─ MailService.php
│  │  ├─ MediaService.php
│  │  ├─ SearchService.php
│  │  ├─ ActivityService.php
│  │  └─ NotificationService.php
│  ├─ Models/*                         # thin models (data access)
│  └─ Security/
│     ├─ Capability.php
│     └─ Validation.php
├─ admin/
│  ├─ SettingsPage.php                 # color system, toggles
│  └─ views/*                          # WP admin UI
├─ public/
│  ├─ index.php                        # no direct access
│  └─ dist/                            # built SPA assets (Vite)
├─ assets/
│  └─ src/                             # React app source
│     ├─ main.tsx
│     ├─ App.tsx
│     ├─ router.tsx
│     ├─ api/
│     ├─ modules/
│     │  ├─ projects/
│     │  ├─ moodboard/
│     │  ├─ whiteboard/
│     │  ├─ calendar/
│     │  ├─ workflow/
│     │  ├─ activities/
│     │  └─ settings/
│     ├─ components/
│     ├─ hooks/
│     ├─ styles/
│     └─ theme/
├─ package.json
├─ vite.config.ts
└─ README.md
```

Key integration points:
- Shortcode `[cyrus_ultimate]` enqueues the SPA bundle on a dedicated page.
- Admin settings built with WP Settings API; exposed to SPA via REST (`GET /settings/theme`).
- `Installer.php` creates/updates the custom tables on activation/update.

---

## Frontend (React) Architecture & UI

- State/Data: TanStack Query per module; optimistic updates where safe.
- Routing: Tabs as nested routes (`/projects`, `/projects/:id`, `/projects/:id/moodboard`, `/whiteboards/:id`, etc.).
- Theming:
  - CSS variables emitted from WP settings: `--color-primary`, `--color-bg`, `--glass-bg`, `--glass-border`, etc.
  - Dark/Light: `data-theme="dark|light"` on `<html>` toggled client-side; default from WP setting.
- Glasmorphism:
  - Semi-transparent layers: `backdrop-filter: blur(12px); background: rgba(var(--glass-bg), .7); border: 1px solid rgba(var(--glass-border), .2)`.
  - Respect contrast; ensure accessible color ratios.
- Components (high-level list):
  - Core: `HeaderUserMenu` (show signed-in name), `ThemeToggle`, `NotificationBell`, `GlobalSearch`, `Breadcrumbs`, `BackButton`.
  - Projects: `ProjectBoard`, `ProjectCard` (timeline progress dots w/ tooltip), `StatusTimelineEditor` (add/edit backdated), `ProjectMediaGrid`, `FullscreenSlider`, `ProjectNotes` (rich text, mentions), `FocusModeHeader` (collapsible), `ProjectAssignees`.
  - Moodboard: `MasonryGrid`, `ImportFromProjectButton`, `LikeButton`, `CommentThread` (with @mentions), `DeleteConfirmModal`.
  - Whiteboard: `WhiteboardCanvas` (react-konva), `GroupSidebar`, `Pin` (draggable/annotatable), `ImagePicker` (from project media), `CommentPopover`.
  - Workflow: `StageColumns` (kanban), `TaskCard`, `AssigneePicker`, `DueDatePicker`, `TaskCommentThread`.
  - Calendar: `CalendarView` (FullCalendar), clickable events → deep link.
  - Activities: `ActivityList`, `FilterChips`.
  - Settings: `ColorPickerGrid`, `ToggleRows`, `SmtpInfoNotice`.

Focus Mode:
- On project detail, a toggle collapses header (height → 0) and elevates content. A persistent `Back` button restores.

Search:
- Global search box in header hits `/search` and shows grouped results. Each tab also has scoped search inputs that add `scope` param.

Images/Slider:
- Use Swiper or an accessible lightbox; preload adjacent images; pinch-zoom on mobile.

Responsive & A11y:
- Mobile-first layouts, hit targets ≥44px, keyboard navigable, ARIA labels, proper landmarks.

---

## Feature Specs (By Module)

### Projects
- Create, view, edit, archive.
- Status timeline: shows ordered dots; hover → date + author + note; click → edit/delete (if allowed).
- Backdated entries: add an event on a past date; updates `current_status` to latest `occurred_at` event.
- Media: upload via WP Media Library; map attachments to `cyrus_media`; show grid; fullscreen slider.
- Notes: rich text with @mentions; edit/delete; versioning optional.
- Focus Mode: collapse header; persistent back button.

### Moodboard
- Auto-sync toggle: if on, any new project media appears on moodboard automatically.
- Manual import button: scan and add missing project images.
- Independent adding: adding images here does NOT add to project unless chosen explicitly.
- Likes + comments with @mentions; delete/edit own comments; admins can moderate.

### Whiteboard
- Boards per project; groups within boards.
- Add images; two-way sync: adding in whiteboard maps into `cyrus_media` for the project.
- Pins on images with text and comments; move/edit/delete pins; mention users in pin comments.
- Optional presence/real-time cursors if using Pusher/Ably.

### Calendar
- Events derived from: project due dates, status milestones (e.g., delivery), task due dates.
- Filters: by project, member, status.
- Click event → deep link to project detail or sub-tab (moodboard/whiteboard).

### Workflow & Tasks
- Define a workflow per project with ordered stages.
- Create tasks, assign members, set due dates, statuses.
- Comments and mentions on tasks; likes optional.
- Kanban drag-and-drop between stages; keyboard accessible controls.

### Activities
- Chronological feed of actions; segment by day/week/month.
- Filters: by project, actor, type.
- Clicking an activity navigates to the relevant resource.

### Notifications & Mentions
- Server detects @mentions in notes/comments/pins/tasks; creates `cyrus_notifications` and `cyrus_mentions` rows.
- Notification center in header with unread badge; mark-as-read individually or all.
- Delivery: in-app; optional email digests (daily/weekly) via WP Cron.

### Authentication & Signup (JWT + Activation Code)
- Login returns tokens; refresh supported; logout revokes refresh.
- Signup requires valid activation code; code can be bound to email and has an expiry; code becomes used on success.
- Admin UI to generate codes and send via email; CSV export optional.

### Settings (WP Admin)
- Theme: set primary color and semantic palette; dark/light default.
- Toggles: enable/disable self-signup, auto-sync to moodboard, real-time provider keys.
- SMTP: rely on `WP Mail SMTP` or equivalent; show read-only status; all mail via `wp_mail`.
- Security: token TTLs, password policy hints.

---

## Security & Compliance

- Sanitize/validate all request data; escape output in admin views.
- Enforce capability checks (per resource ownership/membership).
- JWT best practices: short-lived access tokens (e.g., 15m), refresh token rotation and blacklist on server (DB table or transient cache), issuer/audience checks.
- CORS restricted to site origin; CSRF nonces for sensitive write ops if cookie auth is used in admin.
- Rate-limit login, signup, and invite endpoints; audit log via `cyrus_activities`.
- Store activation codes hashed; never log raw codes.
- File uploads only via WP media; validate mime types; use WP sizes/thumbnails for performance.

---

## Performance Considerations

- Index hot columns; add FULLTEXT on notes/comments.
- Use pagination + cursor-based APIs where needed.
- Cache computed calendars/search with transients keyed by filters.
- Defer image-heavy loads; use responsive images and lazy-loading.
- For real-time, prefer managed services (Pusher/Ably) to avoid running sockets on WP hosting.

---

## Implementation Roadmap (Phased)

Phase 0 — Foundations
- Plugin bootstrap, Composer/Tailwind/Vite setup, shortcode to mount SPA
- DB installer + migrations; roles/capabilities; settings page scaffold
- Auth (JWT login/refresh) + `GET /users/me`

Phase 1 — Projects Core
- Projects CRUD; media mapping; notes with mentions; activity logging
- Status timeline events (add/edit/delete; backdated); ProjectCard timeline UI
- Fullscreen slider and basic search (projects/notes)

Phase 2 — Moodboard
- Moodboard model; auto-sync toggle; manual import; masonry grid
- Likes/comments; notifications on mentions

Phase 3 — Whiteboard
- Whiteboards/groups/items; pins with comments; two-way media sync to project
- Optional real-time presence (if keys configured)

Phase 4 — Calendar
- Aggregate events; FullCalendar UI; filters; deep linking

Phase 5 — Workflow & Tasks
- Workflow + stages; kanban board; tasks CRUD; assignments; comments; due dates

Phase 6 — Invitations & Activation Codes
- Admin UI to generate/send codes; signup endpoint + form; role assignment

Phase 7 — Activities & Notifications
- Activity feed with filters; notification center; mark read; email digests

Phase 8 — Theming & Polish
- Color system settings; dark/light toggle wiring; glasmorphism tuning
- Focus mode header behavior; global vs tab-scoped search; mobile QA; a11y pass

Each phase should have unit/integration tests (PHP + JS), and acceptance criteria.

---

## Testing & Quality

- PHP: PHPUnit for REST controllers/services; integration tests via WP test suite.
- JS: Vitest/Jest + React Testing Library for components; Cypress for E2E.
- Linting: PHP_CodeSniffer (WordPress rules), ESLint + Prettier, Stylelint.
- QA checklist per feature: permissions, a11y, responsive, performance.

---

## Deployment & Build

- Local dev: `composer install` + `npm install` in `assets/`; `npm run dev` for HMR; `npm run build` emits to `public/dist`.
- Plugin zip: exclude dev files; include built assets and `vendor/`.
- DB migrations: bump plugin version to trigger `Installer`.

---

## Admin Settings — Color System (Example)

- Store in `wp_options` under `cyrus_theme_settings`:
  - `primary` (hex), `accent`, `success`, `warning`, `danger`
  - `glassBgRgb` (e.g., `20, 20, 20`), `glassBorderRgb`
  - `defaultTheme` (`light|dark`)
- REST expose: `GET /cyrus/v1/settings/theme` returns CSS variables map
- Frontend applies to `:root` on load, user toggle overrides stored in `localStorage`.

---

## Accessibility & Mobile

- Keyboard navigation across boards, modals, sliders.
- Color contrast compliant; focus states visible; skip links.
- Mobile controls sized appropriately; pinch-zoom in gallery/whiteboard.

---

## Open Questions / Options

- Real-time transport: SSE vs Pusher/Ably. Default to polling + optional Pusher keys.
- Search: MySQL FULLTEXT vs external (Algolia/Elastic) for large datasets.
- Extensible statuses: fixed enum vs user-defined. Start with fixed, plan for extensibility.

---

## Acceptance Criteria (MVP Snapshot)

- Can login with JWT and see own name in header.
- Can create a project, upload images, see timeline dots, add backdated status.
- Can view images fullscreen as slider; add notes with @mentions and receive notifications.
- Can import project images to moodboard, like/comment, and delete own comments.
- Can add whiteboard with groups, add image, pin comments; image appears in project media automatically.
- Can view calendar with status/task events and click-through to project detail.
- Can define workflow stages, create tasks, assign members, and comment.
- Admin can generate/send activation codes; user can signup with code (email+password+name+phone).
- Global search works; dark/light toggle; color system applied; responsive on mobile.

---

## Next Steps (to kick off)

1) Initialize plugin skeleton and Composer dependencies.
2) Build DB installer with the tables above; add roles/capabilities.
3) Implement Auth (JWT login/refresh) + `/users/me`.
4) Scaffold SPA (Vite + Tailwind + React Query + Router) and mount via shortcode.
5) Start Phase 1: Projects core.

This blueprint is designed to evolve; we’ll refine details as we discover specifics during implementation.


