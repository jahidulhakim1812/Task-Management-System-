# User manual

## Everyone
- **Login** at `/tms/` with your email and password. After 5 wrong passwords the account is locked for 15 minutes (the Admin can unlock it).
- **Dark mode** button and **notification bell** are in the top bar. **Search** finds tasks by title or description.
- **My Profile** (bottom-left): edit your name, phone, job title and change your password (8+ characters, letter and number).

## Admin = User and System Management
| Page | What you can do |
|---|---|
| Dashboard | Total users, managers, employees, overall task status, 6-month trend, priority and workload charts, recent activity |
| Users | Add, edit, delete, activate/deactivate users; assign the role Admin / Manager / Employee; reset a password; unlock a locked account |
| All Tasks / Board / Calendar | View every task; create tasks from All Tasks or the Create Task action |
| Reports | Six report types with date, employee and project filters; CSV export and print |
| System Activity | Log of logins, task actions, user changes; filter and export CSV |
| Settings | Company name and the "deadline approaching" warning period |

You cannot deactivate, delete or change the role of your own account. A user who still has tasks cannot be deleted - deactivate instead.

## Manager = Task Creation, Assignment and Monitoring
1. **Create Task**: title, description, employee, project (optional), priority (Low/Medium/High/Urgent), start date, deadline, optional checklist (one item per line).
2. The employee gets a notification. Open the task to edit, reassign, change status, comment, attach files, or delete it.
3. **Tasks** list: search, filter (status, priority, employee, project), sort, export CSV.
4. **Board**: drag cards between Pending / In Progress / Completed.
5. **Calendar**: every deadline of the month. **Projects**: group tasks and see project progress.
6. **Dashboard**: counts, charts, overdue list, tasks due in 7 days, progress per employee.

Admins can create and assign tasks using **Create Task**. Admins can view tasks but cannot edit or change their status.

## Employee = Assigned Task Completion and Status Update
1. **My Tasks / My Dashboard** show only your own tasks.
2. Open a task: read the description, priority and deadline; tick checklist items; add comments and attach files.
3. Press **Start Task** (Pending -> In Progress), later **Mark as Completed**. Status can only move forward one step (also by dragging on **My Board**).
4. **Task History** lists your status updates and completed tasks.

## Task workflow
`Pending -> In Progress -> Completed` (and **Overdue** is shown automatically when the deadline has passed). The manager is notified when an employee completes a task; the manager can reopen a task by moving it back.
