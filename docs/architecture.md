# TruckBill CRM & Onboarding Architecture

## System Overview

```mermaid
flowchart TB
    subgraph website [truckbill.in]
        Forms[Lead Forms]
        OnboardUI[Onboarding SPA]
    end

    subgraph admin [admin.truckbill.in]
        CRM[CRM UI]
        PublicAPI[Public API]
        AdminAPI[Admin API]
        Orchestrator[ProvisioningOrchestrator]
    end

    subgraph products [Product Apps]
        TB[Broker Live]
        OF[OwnFleet]
        PL[Parchun]
    end

    Forms -->|submit.php| PublicAPI
    OnboardUI --> PublicAPI
    CRM --> AdminAPI
    PublicAPI --> Orchestrator
    Orchestrator --> TB
    Orchestrator --> OF
    Orchestrator --> PL
```

## Data Flow Principles

1. **Leads** enter only via website forms or manual CRM entry
2. **Onboarding UI** lives exclusively on `truckbill.in`
3. **Admin** orchestrates onboarding and provisioning — no onboarding UI
4. **Products** receive user creation only after onboarding completes
5. **Staff users** inside tenants are still managed within each product

## Key Components

| Component | Location |
|-----------|----------|
| Lead ingestion | `website/submit.php`, `website/includes/crm-client.php` |
| CRM backend | `admin/app/Http/Controllers/` |
| Onboarding engine | `admin/app/Services/Onboarding*`, `ProvisioningOrchestrator` |
| Onboarding UI | `website/onboarding/` |
| Product provisioning | `*/app/Http/Controllers/Api/OnboardingController.php` |

See also: [ER Diagram](er-diagram.md), [API Docs](api.md), [Authentication](authentication.md), [Deployment](deployment.md)
