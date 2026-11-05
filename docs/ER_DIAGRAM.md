# Database design (ER diagram)

Open this file in VS Code with the "Markdown Preview Mermaid Support" extension, or paste the block into https://mermaid.live to see the diagram.

```mermaid
erDiagram
    USERS ||--o{ TASKS : "is assigned"
    USERS ||--o{ TASKS : "creates"
    USERS ||--o{ PROJECTS : "creates"
    PROJECTS ||--o{ TASKS : "groups"
    TASKS ||--o{ TASK_CHECKLIST : "has"
    TASKS ||--o{ TASK_ATTACHMENTS : "has"
    TASKS ||--o{ TASK_COMMENTS : "has"
    TASKS ||--o{ TASK_HISTORY : "logs status changes"
    TASKS ||--o{ NOTIFICATIONS : "triggers"
    USERS ||--o{ NOTIFICATIONS : "receives"
    USERS ||--o{ ACTIVITY_LOGS : "performs"

    USERS { int id PK
            string full_name
            string email UK
            string password_hash
            enum role "admin | manager | employee"
            bool is_active
            int failed_logins
            datetime locked_until }
    PROJECTS { int id PK
               string name
               text description
               int created_by FK }
    TASKS { int id PK
            string title
            text description
            int project_id FK
            int assigned_to FK
            int created_by FK
            enum priority "Low | Medium | High | Urgent"
            enum status "Pending | In Progress | Completed"
            date start_date
            date deadline
            datetime completed_at }
    TASK_CHECKLIST { int id PK
                     int task_id FK
                     string label
                     bool is_done }
    TASK_ATTACHMENTS { int id PK
                       int task_id FK
                       int user_id FK
                       string original_name
                       string stored_name }
    TASK_COMMENTS { int id PK
                    int task_id FK
                    int user_id FK
                    text comment }
    TASK_HISTORY { int id PK
                   int task_id FK
                   int user_id FK
                   string old_status
                   string new_status }
    NOTIFICATIONS { int id PK
                    int user_id FK
                    int task_id FK
                    string type
                    string message
                    bool is_read }
    ACTIVITY_LOGS { int id PK
                    int user_id FK
                    string action
                    string details
                    string ip_address }
    SETTINGS { string k PK
               string v }
```

**Overdue is not a stored status.** A task is *Overdue* when `status <> 'Completed' AND deadline < today`, so it is always correct without a scheduled job.
