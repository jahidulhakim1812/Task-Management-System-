# Viva - short answers

**What is your project?** A web-based Task Management System for an IT company. Managers create and assign tasks, employees update progress, the admin manages users and the system.

**Why only three roles?** The proposal defines Admin, Manager and Employee. Developer, Designer, Tester and Coder are not separate roles; all staff are Employees.

**What is the task workflow?** Pending -> In Progress -> Completed. If the deadline passes before completion the task is shown as Overdue.

**How is Overdue calculated?** It is not stored. `status <> Completed AND deadline < today` is evaluated when pages load, so it is always correct.

**How do you secure the system?** Password hashing (`password_hash`), PDO prepared statements (SQL injection), output escaping (XSS), CSRF tokens on every POST, role checks on every page, login lockout, session regeneration, upload whitelist, security headers.

**Why PHP, MySQL, XAMPP?** Easy to run locally, widely used, matches the proposal.

**What extra features did you add?** Kanban board, calendar, checklists, attachments, comments, status history, charts, dark mode, CSV export, activity log, settings, installer.

**What would you add next?** E-mail notifications, recurring tasks, time tracking, REST API and a mobile app.
