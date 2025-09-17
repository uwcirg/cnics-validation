# Technical Architecture Diagrams

## 1. Detailed System Architecture

```mermaid
graph TB
    subgraph "Load Balancer Layer"
        LB[Traefik Load Balancer]
    end
    
    subgraph "Application Layer"
        subgraph "MCI Instance"
            MCI_FE[MCI Frontend Container]
            MCI_BE[MCI Backend Container]
            MCI_DB[(MCI MariaDB)]
        end
        
        subgraph "VTE Instance"
            VTE_FE[VTE Frontend Container]
            VTE_BE[VTE Backend Container]
            VTE_DB[(VTE MariaDB)]
        end
        
        subgraph "CVA Instance"
            CVA_FE[CVA Frontend Container]
            CVA_BE[CVA Backend Container]
            CVA_DB[(CVA MariaDB)]
        end
    end
    
    subgraph "Shared Infrastructure"
        AUTH[LDAP Authentication]
        FILES[File Storage]
        LOGS[Centralized Logging]
        MONITOR[Monitoring]
    end
    
    subgraph "External Systems"
        FHIR[FHIR Server]
        EMAIL[SMTP Server]
        CNICS[CNICS Data Warehouse]
    end
    
    LB --> MCI_FE
    LB --> VTE_FE
    LB --> CVA_FE
    
    MCI_FE --> MCI_BE
    VTE_FE --> VTE_BE
    CVA_FE --> CVA_BE
    
    MCI_BE --> MCI_DB
    VTE_BE --> VTE_DB
    CVA_BE --> CVA_DB
    
    MCI_BE --> AUTH
    VTE_BE --> AUTH
    CVA_BE --> AUTH
    
    MCI_BE --> FILES
    VTE_BE --> FILES
    CVA_BE --> FILES
    
    MCI_BE --> LOGS
    VTE_BE --> LOGS
    CVA_BE --> LOGS
    
    MCI_BE --> FHIR
    VTE_BE --> FHIR
    CVA_BE --> FHIR
    
    MCI_BE --> EMAIL
    VTE_BE --> EMAIL
    CVA_BE --> EMAIL
    
    MCI_DB --> CNICS
    VTE_DB --> CNICS
    CVA_DB --> CNICS
```

## 2. Study Configuration Loading

```mermaid
sequenceDiagram
    participant ENV as Environment
    participant CONFIG as Study Config
    participant MODELS as Model Factory
    participant DB as Database
    participant API as API Layer
    
    ENV->>CONFIG: STUDY_TYPE=vte
    CONFIG->>CONFIG: Load VTE Configuration
    CONFIG->>MODELS: Load VTE Models
    MODELS->>DB: Connect to VTE Database
    DB-->>MODELS: VTE Schema Loaded
    MODELS-->>API: VTE Models Available
    API->>API: Initialize VTE Endpoints
    API-->>CONFIG: VTE Instance Ready
```

## 3. Data Flow Architecture

```mermaid
flowchart TD
    subgraph "Data Sources"
        A[CNICS Data Warehouse]
        B[Patient Data Views]
        C[Event Data]
    end
    
    subgraph "Study-Specific Processing"
        D[MCI Data Processing]
        E[VTE Data Processing]
        F[CVA Data Processing]
        G[HF Data Processing]
    end
    
    subgraph "Study Databases"
        H[MCI Database]
        I[VTE Database]
        J[CVA Database]
        K[HF Database]
    end
    
    subgraph "Application Layer"
        L[MCI API]
        M[VTE API]
        N[CVA API]
        O[HF API]
    end
    
    subgraph "User Interface"
        P[MCI Frontend]
        Q[VTE Frontend]
        R[CVA Frontend]
        S[HF Frontend]
    end
    
    A --> B
    B --> C
    C --> D
    C --> E
    C --> F
    C --> G
    
    D --> H
    E --> I
    F --> J
    G --> K
    
    H --> L
    I --> M
    J --> N
    K --> O
    
    L --> P
    M --> Q
    N --> R
    O --> S
```

## 4. Security and Access Control

```mermaid
graph TB
    subgraph "Authentication Layer"
        LDAP[LDAP Server]
        APACHE[Apache/LDAP Integration]
    end
    
    subgraph "Authorization Layer"
        ROLES[Role-Based Access Control]
        PERMS[Study-Specific Permissions]
    end
    
    subgraph "Study Access Control"
        MCI_AUTH[MCI Users]
        VTE_AUTH[VTE Users]
        CVA_AUTH[CVA Users]
        HF_AUTH[HF Users]
    end
    
    subgraph "Data Isolation"
        MCI_ISO[MCI Data Isolation]
        VTE_ISO[VTE Data Isolation]
        CVA_ISO[CVA Data Isolation]
        HF_ISO[HF Data Isolation]
    end
    
    LDAP --> APACHE
    APACHE --> ROLES
    ROLES --> PERMS
    
    PERMS --> MCI_AUTH
    PERMS --> VTE_AUTH
    PERMS --> CVA_AUTH
    PERMS --> HF_AUTH
    
    MCI_AUTH --> MCI_ISO
    VTE_AUTH --> VTE_ISO
    CVA_AUTH --> CVA_ISO
    HF_AUTH --> HF_ISO
```

## 5. Deployment Pipeline

```mermaid
flowchart LR
    subgraph "Development"
        A[Code Changes]
        B[Study-Specific Config]
        C[Local Testing]
    end
    
    subgraph "Staging"
        D[Build Images]
        E[Deploy to Staging]
        F[Integration Testing]
    end
    
    subgraph "Production"
        G[Production Build]
        H[Blue-Green Deployment]
        I[Health Checks]
        J[Rollback if Needed]
    end
    
    A --> B
    B --> C
    C --> D
    D --> E
    E --> F
    F --> G
    G --> H
    H --> I
    I --> J
    J --> H
```

## 6. Monitoring and Observability

```mermaid
graph TB
    subgraph "Application Metrics"
        MCI_METRICS[MCI Metrics]
        VTE_METRICS[VTE Metrics]
        CVA_METRICS[CVA Metrics]
    end
    
    subgraph "Infrastructure Metrics"
        CPU[CPU Usage]
        MEM[Memory Usage]
        DISK[Disk Usage]
        NET[Network Usage]
    end
    
    subgraph "Business Metrics"
        EVENTS[Event Processing]
        REVIEWS[Review Completion]
        USERS[Active Users]
        ERRORS[Error Rates]
    end
    
    subgraph "Monitoring Stack"
        PROMETHEUS[Prometheus]
        GRAFANA[Grafana]
        ALERTS[Alert Manager]
        LOGS[Centralized Logging]
    end
    
    MCI_METRICS --> PROMETHEUS
    VTE_METRICS --> PROMETHEUS
    CVA_METRICS --> PROMETHEUS
    
    CPU --> PROMETHEUS
    MEM --> PROMETHEUS
    DISK --> PROMETHEUS
    NET --> PROMETHEUS
    
    EVENTS --> PROMETHEUS
    REVIEWS --> PROMETHEUS
    USERS --> PROMETHEUS
    ERRORS --> PROMETHEUS
    
    PROMETHEUS --> GRAFANA
    PROMETHEUS --> ALERTS
    PROMETHEUS --> LOGS
```

## 7. Disaster Recovery and Backup

```mermaid
graph TB
    subgraph "Primary Site"
        MCI_PRIMARY[MCI Production]
        VTE_PRIMARY[VTE Production]
        CVA_PRIMARY[CVA Production]
    end
    
    subgraph "Backup Strategy"
        DB_BACKUP[Database Backups]
        FILE_BACKUP[File Backups]
        CONFIG_BACKUP[Config Backups]
    end
    
    subgraph "Disaster Recovery"
        DR_SITE[DR Site]
        RESTORE[Restore Procedures]
        FAILOVER[Failover Process]
    end
    
    MCI_PRIMARY --> DB_BACKUP
    VTE_PRIMARY --> DB_BACKUP
    CVA_PRIMARY --> DB_BACKUP
    
    MCI_PRIMARY --> FILE_BACKUP
    VTE_PRIMARY --> FILE_BACKUP
    CVA_PRIMARY --> FILE_BACKUP
    
    DB_BACKUP --> DR_SITE
    FILE_BACKUP --> DR_SITE
    CONFIG_BACKUP --> DR_SITE
    
    DR_SITE --> RESTORE
    RESTORE --> FAILOVER
```

## 8. Cost and Resource Optimization

```mermaid
graph LR
    subgraph "Resource Sharing"
        A[Shared Infrastructure]
        B[Common Components]
        C[Unified Monitoring]
    end
    
    subgraph "Study Isolation"
        D[Separate Databases]
        E[Independent Scaling]
        F[Isolated Deployments]
    end
    
    subgraph "Cost Benefits"
        G[Reduced Maintenance]
        H[Shared Development]
        I[Unified Security]
        J[Efficient Resource Use]
    end
    
    A --> G
    B --> H
    C --> I
    D --> J
    E --> J
    F --> J
```
