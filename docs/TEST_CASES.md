# Test cases (all executed against the demo data)

| # | Area | Test | Expected | Result |
|---|---|---|---|---|
| 1 | Login | Admin / Manager / Employee log in | Redirect to own dashboard | Pass |
| 2 | Login | Wrong password 5 times, then correct password | Account locked 15 min; correct password refused; Admin unlock works | Pass |
| 3 | Login | Deactivated user logs in | "Account is deactivated" | Pass |
| 4 | Access | Employee opens a Manager or Admin page | 403 | Pass |
| 5 | Access | Manager opens Admin settings; Admin opens a task edit form | 403 | Pass |
| 6 | Access | Employee opens a task assigned to someone else | 404 | Pass |
| 7 | Security | POST without CSRF token | Rejected (419 / JSON error) | Pass |
| 8 | Task | Manager creates task with checklist and assigns it | Task saved with status Pending, checklist items saved, employee notified | Pass |
| 8a | Task | Admin creates task and assigns it | Task saved with status Pending, employee notified; Admin cannot edit or change its status | Pass |
| 9 | Status | Employee Pending -> In Progress -> Completed | Allowed, history rows written, manager notified | Pass |
| 10 | Status | Employee tries Pending -> Completed, or backwards | Refused | Pass |
| 11 | Status | Manager moves Completed -> Pending | Allowed, completed date cleared, employee notified | Pass |
| 12 | Status | Admin tries to change a status | Refused | Pass |
| 13 | Board | Drag a card (AJAX) | Same rules as buttons; column counts update | Pass |
| 14 | Checklist | Tick, add, remove items (owner / manager) | Saved; Admin cannot edit | Pass |
| 15 | Files | Upload .txt, upload .php | .txt stored; .php refused | Pass |
| 16 | Files | Download by owner / by another employee | Allowed / 404 | Pass |
| 17 | Files | Delete task | Attachment rows and files removed | Pass |
| 18 | Comments | Post a comment containing HTML | Saved and shown escaped (no XSS) | Pass |
| 19 | Users | Add user with weak password / duplicate email | Rejected | Pass |
| 20 | Users | Delete a user who has tasks | Refused with a message | Pass |
| 21 | Notifications | Deadline soon / overdue | Created once per task and user | Pass |
| 22 | Reports | Overall totals vs. SQL count | Equal (29 / 16 completed / 3 overdue in demo run) | Pass |
| 23 | Reports | CSV export (tasks, reports, activity) | Valid CSV file | Pass |
| 24 | Settings | Change company name | Shown in sidebar and titles | Pass |
| 25 | Pages | All pages for all three roles | HTTP 200, no PHP warnings | Pass |
