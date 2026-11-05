-- ============================================================
--  Task Management System (TMS) v2 - ABC IT Solutions
--  Import in phpMyAdmin (Import tab), run:  mysql -u root < database/schema.sql
--  or open  http://localhost/tms/install.php  and click Install.
--  Demo logins:  admin@abcit.com / Admin@123
--                manager@abcit.com (or manager2@) / Manager@123
--                employee01@abcit.com ... employee08@abcit.com / Employee@123
-- ============================================================
CREATE DATABASE IF NOT EXISTS tms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tms_db;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS settings, task_attachments, task_checklist, activity_logs, notifications, task_comments, task_history, tasks, projects, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','employee') NOT NULL DEFAULT 'employee',
  phone VARCHAR(30) NULL,
  job_title VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_role (role)
) ENGINE=InnoDB;

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Overdue is NOT stored: it is derived (status <> Completed AND deadline < today)
CREATE TABLE tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  project_id INT NULL,
  assigned_to INT NOT NULL,
  created_by INT NOT NULL,
  priority ENUM('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
  status ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  start_date DATE NOT NULL,
  deadline DATE NOT NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_assigned (assigned_to, status),
  INDEX idx_deadline (deadline),
  INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE task_checklist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  label VARCHAR(200) NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE task_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  user_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE task_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL, user_id INT NOT NULL, comment TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE task_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL, user_id INT NOT NULL,
  old_status VARCHAR(20) NOT NULL, new_status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, task_id INT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'info',
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL, action VARCHAR(50) NOT NULL, details VARCHAR(255) NULL, ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_action (action)
) ENGINE=InnoDB;

CREATE TABLE settings (
  k VARCHAR(60) PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ---------------- DEMO DATA (dates are relative to the import day) ----------------
INSERT INTO settings (k, v) VALUES ('company_name','ABC IT Solutions'),('warning_days','2');

INSERT INTO users (id, full_name, email, password_hash, role, job_title, created_at) VALUES
(1,'System Admin','admin@abcit.com','$2y$10$QZnp/8ezZXZksLQgIEF8JOyNy730GapLxmz6zvanatsWvqwXWNawK','admin','IT Administrator',DATE_SUB(NOW(), INTERVAL 160 DAY)),
(2,'Manager Rahman','manager@abcit.com','$2y$10$b14S7QBk4pnafaVVHgIy1uvH.3phjylCtolI8t.2.2FQcwHntQnGa','manager','Project Manager',DATE_SUB(NOW(), INTERVAL 150 DAY)),
(3,'Manager Sultana','manager2@abcit.com','$2y$10$b14S7QBk4pnafaVVHgIy1uvH.3phjylCtolI8t.2.2FQcwHntQnGa','manager','Operations Manager',DATE_SUB(NOW(), INTERVAL 140 DAY)),
(4,'Employee 01','employee01@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 130 DAY)),
(5,'Employee 02','employee02@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 120 DAY)),
(6,'Employee 03','employee03@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 110 DAY)),
(7,'Employee 04','employee04@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 95 DAY)),
(8,'Employee 05','employee05@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 80 DAY)),
(9,'Employee 06','employee06@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 65 DAY)),
(10,'Employee 07','employee07@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 45 DAY)),
(11,'Employee 08','employee08@abcit.com','$2y$10$s4jSKvP3pnKYBXqTwl4xqu8qLflNeMg0qS8.yuzLXsRnETIrBx7hu','employee','Staff Member',DATE_SUB(NOW(), INTERVAL 5 MINUTE));

INSERT INTO projects (id, name, description, created_by) VALUES
(1,'Client Website Project','New corporate website for a client of ABC IT Solutions',2),
(2,'Internal Operations','Reports, documentation and office activities',2),
(3,'Mobile App Support','Support and maintenance work for the client mobile app',3),
(4,'Client Onboarding 2026','Meetings, data collection and onboarding paperwork',3);

INSERT INTO tasks (id,title,description,project_id,assigned_to,created_by,priority,status,start_date,deadline,completed_at,created_at) VALUES
(1,'Website Content Update','Update the old information on the client website.',1,4,2,'High','In Progress',DATE(DATE_SUB(NOW(), INTERVAL 3 DAY)),DATE_ADD(CURDATE(), INTERVAL 4 DAY),NULL,DATE_SUB(NOW(), INTERVAL 72 HOUR)),
(2,'Arrange Client Meeting','Fix a time and prepare the meeting room and agenda.',4,5,2,'Medium','Completed',DATE(DATE_SUB(NOW(), INTERVAL 9 DAY)),DATE_SUB(CURDATE(), INTERVAL 3 DAY),DATE_SUB(NOW(), INTERVAL 96 HOUR),DATE_SUB(NOW(), INTERVAL 216 HOUR)),
(3,'Prepare Project Documentation','Write the project documentation for handover.',1,6,2,'Medium','Pending',DATE(DATE_SUB(NOW(), INTERVAL 1 DAY)),DATE_ADD(CURDATE(), INTERVAL 6 DAY),NULL,DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(4,'Update Client Information','Update client records in the company database.',4,7,3,'Low','Pending',DATE(DATE_SUB(NOW(), INTERVAL 1 DAY)),DATE_ADD(CURDATE(), INTERVAL 9 DAY),NULL,DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(5,'Prepare Monthly Project Report','Prepare the monthly progress report of the project.',2,8,2,'High','In Progress',DATE(DATE_SUB(NOW(), INTERVAL 4 DAY)),DATE_ADD(CURDATE(), INTERVAL 1 DAY),NULL,DATE_SUB(NOW(), INTERVAL 96 HOUR)),
(6,'Prepare Presentation for Client Meeting','Slides for the client meeting.',4,5,2,'Urgent','In Progress',DATE(DATE_SUB(NOW(), INTERVAL 5 DAY)),DATE_ADD(CURDATE(), INTERVAL 2 DAY),NULL,DATE_SUB(NOW(), INTERVAL 120 HOUR)),
(7,'Collect Client Feedback Forms','Collect and file the signed feedback forms.',4,4,3,'Medium','Pending',DATE(DATE_SUB(NOW(), INTERVAL 9 DAY)),DATE_SUB(CURDATE(), INTERVAL 2 DAY),NULL,DATE_SUB(NOW(), INTERVAL 216 HOUR)),
(8,'Backup Project Files','Create a backup of all finished project files.',2,6,2,'Low','Completed',DATE(DATE_SUB(NOW(), INTERVAL 12 DAY)),DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_SUB(NOW(), INTERVAL 144 HOUR),DATE_SUB(NOW(), INTERVAL 288 HOUR)),
(9,'Fix Login Page Layout','Login page breaks on small phone screens.',3,9,3,'High','In Progress',DATE(DATE_SUB(NOW(), INTERVAL 8 DAY)),DATE_SUB(CURDATE(), INTERVAL 1 DAY),NULL,DATE_SUB(NOW(), INTERVAL 192 HOUR)),
(10,'Prepare Quarterly Budget Sheet','Collect department costs into the budget sheet.',2,10,2,'High','Pending',DATE(DATE_SUB(NOW(), INTERVAL 6 DAY)),DATE_SUB(CURDATE(), INTERVAL 3 DAY),NULL,DATE_SUB(NOW(), INTERVAL 144 HOUR)),
(11,'Server Maintenance Checklist','Monthly server health check and log cleanup.',2,11,2,'Medium','Pending',DATE(DATE_SUB(NOW(), INTERVAL 2 DAY)),DATE_ADD(CURDATE(), INTERVAL 7 DAY),NULL,DATE_SUB(NOW(), INTERVAL 48 HOUR)),
(12,'Write User Manual','User manual for the mobile app release.',3,7,3,'Medium','In Progress',DATE(DATE_SUB(NOW(), INTERVAL 6 DAY)),DATE_ADD(CURDATE(), INTERVAL 10 DAY),NULL,DATE_SUB(NOW(), INTERVAL 144 HOUR)),
(13,'Test Payment Gateway Flow','Test every payment path before release.',3,9,3,'Urgent','Pending',DATE(DATE_SUB(NOW(), INTERVAL 1 DAY)),DATE_ADD(CURDATE(), INTERVAL 3 DAY),NULL,DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(14,'Client Kickoff Meeting Notes','Write and share the kickoff meeting notes.',4,8,2,'Low','Completed',DATE(DATE_SUB(NOW(), INTERVAL 20 DAY)),DATE_SUB(CURDATE(), INTERVAL 15 DAY),DATE_SUB(NOW(), INTERVAL 384 HOUR),DATE_SUB(NOW(), INTERVAL 480 HOUR)),
(15,'Update Company Profile Page','Refresh the about and team pages.',1,4,2,'Medium','Completed',DATE(DATE_SUB(NOW(), INTERVAL 26 DAY)),DATE_SUB(CURDATE(), INTERVAL 18 DAY),DATE_SUB(NOW(), INTERVAL 456 HOUR),DATE_SUB(NOW(), INTERVAL 624 HOUR)),
(16,'Create Employee Onboarding Pack','Welcome pack for the new joiners.',2,10,3,'Medium','Completed',DATE(DATE_SUB(NOW(), INTERVAL 33 DAY)),DATE_SUB(CURDATE(), INTERVAL 23 DAY),DATE_SUB(NOW(), INTERVAL 576 HOUR),DATE_SUB(NOW(), INTERVAL 792 HOUR)),
(17,'SEO Report for Client Website','Monthly SEO ranking summary.',1,5,2,'Medium','Completed',DATE(DATE_SUB(NOW(), INTERVAL 41 DAY)),DATE_SUB(CURDATE(), INTERVAL 34 DAY),DATE_SUB(NOW(), INTERVAL 768 HOUR),DATE_SUB(NOW(), INTERVAL 984 HOUR)),
(18,'Design Invoice Template','New invoice template with the company branding.',2,11,2,'High','Completed',DATE(DATE_SUB(NOW(), INTERVAL 48 DAY)),DATE_SUB(CURDATE(), INTERVAL 42 DAY),DATE_SUB(NOW(), INTERVAL 1032 HOUR),DATE_SUB(NOW(), INTERVAL 1152 HOUR)),
(19,'Mobile App Bug Triage','Review and sort the reported app bugs.',3,9,3,'High','Completed',DATE(DATE_SUB(NOW(), INTERVAL 55 DAY)),DATE_SUB(CURDATE(), INTERVAL 50 DAY),DATE_SUB(NOW(), INTERVAL 1224 HOUR),DATE_SUB(NOW(), INTERVAL 1320 HOUR)),
(20,'Data Entry - Client Contracts','Enter the signed client contracts in the system.',4,7,3,'Low','Completed',DATE(DATE_SUB(NOW(), INTERVAL 63 DAY)),DATE_SUB(CURDATE(), INTERVAL 54 DAY),DATE_SUB(NOW(), INTERVAL 1320 HOUR),DATE_SUB(NOW(), INTERVAL 1512 HOUR)),
(21,'Prepare Training Session Slides','Slides for the internal training.',2,6,2,'Medium','Completed',DATE(DATE_SUB(NOW(), INTERVAL 72 DAY)),DATE_SUB(CURDATE(), INTERVAL 65 DAY),DATE_SUB(NOW(), INTERVAL 1584 HOUR),DATE_SUB(NOW(), INTERVAL 1728 HOUR)),
(22,'Website Performance Audit','Check page speed and fix the top issues.',1,4,2,'High','Completed',DATE(DATE_SUB(NOW(), INTERVAL 84 DAY)),DATE_SUB(CURDATE(), INTERVAL 74 DAY),DATE_SUB(NOW(), INTERVAL 1728 HOUR),DATE_SUB(NOW(), INTERVAL 2016 HOUR)),
(23,'Arrange Team Lunch Event','Plan the quarterly team lunch.',2,10,3,'Low','Completed',DATE(DATE_SUB(NOW(), INTERVAL 95 DAY)),DATE_SUB(CURDATE(), INTERVAL 89 DAY),DATE_SUB(NOW(), INTERVAL 2160 HOUR),DATE_SUB(NOW(), INTERVAL 2280 HOUR)),
(24,'Client Feedback Analysis','Summarize the survey results.',4,8,2,'Medium','Completed',DATE(DATE_SUB(NOW(), INTERVAL 108 DAY)),DATE_SUB(CURDATE(), INTERVAL 99 DAY),DATE_SUB(NOW(), INTERVAL 2400 HOUR),DATE_SUB(NOW(), INTERVAL 2592 HOUR)),
(25,'Update Asset Register','List all office laptops and licences.',2,11,2,'Low','Completed',DATE(DATE_SUB(NOW(), INTERVAL 121 DAY)),DATE_SUB(CURDATE(), INTERVAL 109 DAY),DATE_SUB(NOW(), INTERVAL 2640 HOUR),DATE_SUB(NOW(), INTERVAL 2904 HOUR)),
(26,'Prepare Annual Report Draft','First draft of the annual report.',2,6,2,'High','Completed',DATE(DATE_SUB(NOW(), INTERVAL 135 DAY)),DATE_SUB(CURDATE(), INTERVAL 121 DAY),DATE_SUB(NOW(), INTERVAL 2856 HOUR),DATE_SUB(NOW(), INTERVAL 3240 HOUR)),
(27,'Setup Test Devices','Prepare phones for app testing.',3,9,3,'Medium','Completed',DATE(DATE_SUB(NOW(), INTERVAL 147 DAY)),DATE_SUB(CURDATE(), INTERVAL 140 DAY),DATE_SUB(NOW(), INTERVAL 3384 HOUR),DATE_SUB(NOW(), INTERVAL 3528 HOUR)),
(28,'Review Vendor Proposals','Compare three vendor proposals.',2,5,2,'Medium','In Progress',DATE(DATE_SUB(NOW(), INTERVAL 3 DAY)),DATE_ADD(CURDATE(), INTERVAL 5 DAY),NULL,DATE_SUB(NOW(), INTERVAL 72 HOUR));

INSERT INTO task_history (task_id,user_id,old_status,new_status,created_at) VALUES
(1,4,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 48 HOUR)),
(2,5,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 192 HOUR)),
(2,5,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 96 HOUR)),
(5,8,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 72 HOUR)),
(6,5,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 96 HOUR)),
(8,6,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 264 HOUR)),
(8,6,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 144 HOUR)),
(9,9,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 168 HOUR)),
(12,7,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 120 HOUR)),
(14,8,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 456 HOUR)),
(14,8,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 384 HOUR)),
(15,4,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 600 HOUR)),
(15,4,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 456 HOUR)),
(16,10,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 768 HOUR)),
(16,10,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 576 HOUR)),
(17,5,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 960 HOUR)),
(17,5,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 768 HOUR)),
(18,11,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 1128 HOUR)),
(18,11,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 1032 HOUR)),
(19,9,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 1296 HOUR)),
(19,9,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 1224 HOUR)),
(20,7,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 1488 HOUR)),
(20,7,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 1320 HOUR)),
(21,6,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 1704 HOUR)),
(21,6,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 1584 HOUR)),
(22,4,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 1992 HOUR)),
(22,4,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 1728 HOUR)),
(23,10,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 2256 HOUR)),
(23,10,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 2160 HOUR)),
(24,8,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 2568 HOUR)),
(24,8,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 2400 HOUR)),
(25,11,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 2880 HOUR)),
(25,11,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 2640 HOUR)),
(26,6,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 3216 HOUR)),
(26,6,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 2856 HOUR)),
(27,9,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 3504 HOUR)),
(27,9,'In Progress','Completed',DATE_SUB(NOW(), INTERVAL 3384 HOUR)),
(28,5,'Pending','In Progress',DATE_SUB(NOW(), INTERVAL 48 HOUR));

INSERT INTO task_checklist (task_id,label,is_done) VALUES
(1,'Update home page',1),
(1,'Update about page',1),
(1,'Update contact page',0),
(1,'Client approval',0),
(5,'Collect progress data',1),
(5,'Write summary',0),
(5,'Add charts',0),
(6,'Draft outline',1),
(6,'Design slides',1),
(6,'Rehearse',0),
(12,'Write setup section',1),
(12,'Add screenshots',0),
(13,'Card payments',0),
(13,'Mobile wallet payments',0),
(13,'Refund flow',0);

INSERT INTO task_comments (task_id,user_id,comment,created_at) VALUES
(1,4,'Started with the home and about pages.',DATE_SUB(NOW(), INTERVAL 40 HOUR)),
(1,2,'Good. Please finish the contact page before the client review.',DATE_SUB(NOW(), INTERVAL 30 HOUR)),
(5,8,'Data collected. Writing the summary now.',DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(9,9,'Fixed on iOS, still checking small Android screens.',DATE_SUB(NOW(), INTERVAL 5 HOUR));

INSERT INTO notifications (user_id,task_id,type,message,is_read) VALUES
(4,1,'assigned','New task assigned: "Website Content Update" - High priority.',1),
(8,5,'deadline','Deadline approaching for "Prepare Monthly Project Report".',0),
(2,2,'completed','Employee 02 completed "Arrange Client Meeting".',1),
(9,9,'overdue','Task "Fix Login Page Layout" is overdue.',0);

INSERT INTO activity_logs (user_id,action,details) VALUES
(1,'user_create','Demo users created'),(2,'project_create','Client Website Project'),(2,'task_create','Website Content Update'),
(4,'task_status','Website Content Update: Pending -> In Progress'),(1,'login','User logged in');
