# TruckBill CRM — Entity Relationship Diagram

```mermaid
erDiagram
    users ||--o{ leads : assigns
    users ||--o{ users : manages
    users ||--o{ lead_notes : writes
    leads ||--o{ lead_notes : has
    leads ||--o{ onboarding_invitations : generates
    leads ||--o| companies : converts_to
    onboarding_invitations ||--|| onboarding_sessions : tracks
    onboarding_invitations ||--o{ provisioning_logs : provisions
    users ||--o{ onboarding_invitations : creates

    users {
        bigint id PK
        bigint manager_id FK
        string name
        string email
    }

    leads {
        bigint id PK
        string name
        string email
        string status
        string product
        string source
        bigint assigned_to FK
    }

    lead_notes {
        bigint id PK
        bigint lead_id FK
        bigint user_id FK
        text body
    }

    onboarding_invitations {
        bigint id PK
        bigint lead_id FK
        string product
        string email
        string token_hash
        timestamp expires_at
    }

    onboarding_sessions {
        bigint id PK
        bigint onboarding_invitation_id FK
        timestamp email_verified_at
        timestamp policy_accepted_at
    }

    provisioning_logs {
        bigint id PK
        string idempotency_key UK
        string product
        string status
    }
```

## Product databases (external)

Each product maintains its own `users` table with mandatory fields:

- `email_verified_at`
- `policy_accepted_at`

Provisioned via signed `POST /api/onboarding/create-user`.
