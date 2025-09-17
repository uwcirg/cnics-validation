# CNICS Validation Multi-Study Architecture Diagrams

## 1. Overall System Architecture

```mermaid
graph TB
    subgraph "Single Repository"
        subgraph "Shared Core Components"
            A[Authentication & Authorization]
            B[File Upload/Download]
            C[Event Management]
            D[User Management]
            E[API Framework]
        end
        
        subgraph "Study-Specific Components"
            F[VTE Models & UI]
            G[CVA Models & UI]
            H[Heart Failure Models & UI]
            I[AFIB Models & UI]
            J[Malignancy Models & UI]
            K[MCI Models & UI]
        end
        
        subgraph "Configuration Layer"
            L[Study Config Factory]
            M[Environment Variables]
            N[Docker Compose Overrides]
        end
    end
    
    subgraph "Separate Deployments"
        subgraph "MCI Deployment"
            O[MCI Containers]
            P[MCI Database]
            Q[mci-validation.cirg.uw.edu]
        end
        
        subgraph "VTE Deployment"
            R[VTE Containers]
            S[VTE Database]
            T[vte-validation.cirg.uw.edu]
        end
        
        subgraph "CVA Deployment"
            U[CVA Containers]
            V[CVA Database]
            W[cva-validation.cirg.uw.edu]
        end
        
        subgraph "Other Studies"
            X[HF Containers]
            Y[AFIB Containers]
            Z[Malignancy Containers]
        end
    end
    
    L --> F
    L --> G
    L --> H
    L --> I
    L --> J
    L --> K
    
    M --> O
    M --> R
    M --> U
    
    N --> O
    N --> R
    N --> U
```

## 2. Study Configuration Flow

```mermaid
flowchart TD
    A[Environment Variable: STUDY_TYPE] --> B{Study Type?}
    
    B -->|mci| C[Load MCI Configuration]
    B -->|vte| D[Load VTE Configuration]
    B -->|cva| E[Load CVA Configuration]
    B -->|hf| F[Load Heart Failure Configuration]
    B -->|afib| G[Load AFIB Configuration]
    B -->|malignancy| H[Load Malignancy Configuration]
    
    C --> I[Use Current Models]
    D --> J[Load VTE Models]
    E --> K[Load CVA Models]
    F --> L[Load HF Models]
    G --> M[Load AFIB Models]
    H --> N[Load Malignancy Models]
    
    I --> O[Deploy MCI Instance]
    J --> P[Deploy VTE Instance]
    K --> Q[Deploy CVA Instance]
    L --> R[Deploy HF Instance]
    M --> S[Deploy AFIB Instance]
    N --> T[Deploy Malignancy Instance]
```

## 3. Database Schema Strategy

```mermaid
graph TB
    subgraph "Shared Core Tables"
        EVENTS[Events Table<br/>- id (PK)<br/>- patient_id (FK)<br/>- creator_id (FK)<br/>- status<br/>- created_date]
        USERS[Users Table<br/>- id (PK)<br/>- login<br/>- name<br/>- admin_flag<br/>- uploader_flag<br/>- reviewer_flag]
        LOGS[Logs Table<br/>- id (PK)<br/>- user_id (FK)<br/>- action<br/>- timestamp]
        PATIENTS[Patients Table<br/>- id (PK)<br/>- site_patient_id<br/>- site]
        CRITERIAS[Criterias Table<br/>- id (PK)<br/>- event_id (FK)<br/>- criteria_text]
    end
    
    subgraph "Study-Specific Review Tables"
        MCI_REVIEWS[MCI Reviews<br/>- id (PK)<br/>- event_id (FK)<br/>- reviewer_id (FK)<br/>- outcome<br/>- chest_pain_flag<br/>- ecg_changes_flag<br/>- ecg_type]
        
        VTE_REVIEWS[VTE Reviews<br/>- id (PK)<br/>- event_id (FK)<br/>- reviewer_id (FK)<br/>- outcome<br/>- pe_flag, dvt_flag<br/>- vte_type<br/>- anticoagulation<br/>- risk_factors]
        
        CVA_REVIEWS[CVA Reviews<br/>- id (PK)<br/>- event_id (FK)<br/>- reviewer_id (FK)<br/>- outcome<br/>- stroke_type<br/>- nihss_score<br/>- thrombolysis<br/>- mechanical_thrombectomy]
        
        HF_REVIEWS[HF Reviews<br/>- id (PK)<br/>- event_id (FK)<br/>- reviewer_id (FK)<br/>- outcome<br/>- hf_type<br/>- lvef (1-80%)<br/>- hf_classification<br/>- congestion]
        
        AFIB_REVIEWS[AFIB Reviews<br/>- id (PK)<br/>- event_id (FK)<br/>- reviewer_id (FK)<br/>- outcome<br/>- afib_flag, aflutter_flag<br/>- af_type<br/>- associated_conditions<br/>- anticoagulation]
    end
    
    subgraph "Study-Specific Additional Tables"
        QUESTIONNAIRES[Questionnaires<br/>- id (PK)<br/>- event_id (FK)<br/>- questionnaire_type<br/>- data (JSON)<br/>- completed_date]
    end
    
    %% Relationships
    EVENTS -->|1:many| MCI_REVIEWS
    EVENTS -->|1:many| VTE_REVIEWS
    EVENTS -->|1:many| CVA_REVIEWS
    EVENTS -->|1:many| HF_REVIEWS
    EVENTS -->|1:many| AFIB_REVIEWS
    EVENTS -->|1:many| QUESTIONNAIRES
    EVENTS -->|1:many| CRITERIAS
    
    USERS -->|1:many| MCI_REVIEWS
    USERS -->|1:many| VTE_REVIEWS
    USERS -->|1:many| CVA_REVIEWS
    USERS -->|1:many| HF_REVIEWS
    USERS -->|1:many| AFIB_REVIEWS
    USERS -->|1:many| LOGS
    
    PATIENTS -->|1:many| EVENTS
```

## 4. Deployment Architecture

```mermaid
graph TB
    subgraph "Development Environment"
        A[Single Codebase]
        B[Study Config Factory]
        C[Commented Study Code]
    end
    
    subgraph "Production Deployments"
        subgraph "MCI Production"
            D[MCI Containers]
            E[cnics_mci_validation DB]
            F[mci-validation.cirg.uw.edu]
        end
        
        subgraph "VTE Production"
            G[VTE Containers]
            H[cnics_vte_validation DB]
            I[vte-validation.cirg.uw.edu]
        end
        
        subgraph "CVA Production"
            J[CVA Containers]
            K[cnics_cva_validation DB]
            L[cva-validation.cirg.uw.edu]
        end
        
        subgraph "Other Studies"
            M[HF Production]
            N[AFIB Production]
            O[Malignancy Production]
        end
    end
    
    subgraph "Legacy CakePHP Systems"
        P[MCI CakePHP]
        Q[VTE CakePHP]
        R[CVA CakePHP]
        S[HF CakePHP]
        T[AFIB CakePHP]
    end
    
    A --> D
    A --> G
    A --> J
    A --> M
    A --> N
    A --> O
    
    P -.->|Migrate| D
    Q -.->|Migrate| G
    R -.->|Migrate| J
    S -.->|Migrate| M
    T -.->|Migrate| N
```

## 5. Study-Specific Workflows

```mermaid
flowchart TD
    subgraph "Common Workflow"
        A[Event Created] --> B[Packet Uploaded]
        B --> C[Packet Scrubbed]
        C --> D[Event Screened]
        D --> E[Event Assigned]
        E --> F[Event Reviewed]
        F --> G[Event Completed]
    end
    
    subgraph "VTE-Specific Workflow"
        H[Event Created] --> I[Packet Uploaded]
        I --> J[Packet Pre-scrubbed]
        J --> K{Pre-scrub Result}
        K -->|Pass| L[Packet Scrubbed]
        K -->|Reject| M[Pre-scrub Rejected]
        M --> N[Packet Re-uploaded]
        N --> J
        L --> O[Event Screened]
        O --> P[Event Assigned]
        P --> Q[Event Reviewed]
        Q --> R[Event Completed]
    end
    
    subgraph "CVA-Specific Workflow"
        S[Event Created] --> T[Packet Uploaded]
        T --> U[Packet Scrubbed]
        U --> V[Questionnaire Completed]
        V --> W[Event Screened]
        W --> X[Event Assigned]
        X --> Y[Event Reviewed]
        Y --> Z[Event Completed]
    end
```

## 6. Migration Strategy from CakePHP

```mermaid
gantt
    title CNICS Validation Migration Timeline
    dateFormat  YYYY-MM-DD
    section Phase 1: Foundation
    MCI Modernization    :done, mci, 2024-01-01, 2024-06-30
    Study Config System  :done, config, 2024-03-01, 2024-04-30
    Shared Components    :done, shared, 2024-02-01, 2024-05-31
    
    section Phase 2: Active Studies
    VTE Migration        :active, vte, 2024-07-01, 2024-10-31
    CVA Migration        :cva, 2024-08-01, 2024-11-30
    Heart Failure Migration :hf, 2024-09-01, 2024-12-31
    
    section Phase 3: Future Studies
    AFIB Migration       :afib, 2025-01-01, 2025-03-31
    Malignancy Integration :malignancy, 2025-02-01, 2025-04-30
    
    section Legacy Maintenance
    CakePHP Support      :legacy, 2024-01-01, 2025-06-30
```

## 7. Technology Stack Comparison

```mermaid
graph LR
    subgraph "Legacy CakePHP Systems"
        A[CakePHP v1.x]
        B[MySQL]
        C[Apache/LDAP]
        D[Manual Deployment]
    end
    
    subgraph "Modern CNICS Validation"
        E[React Frontend]
        F[Flask Backend]
        G[MariaDB]
        H[Docker Containers]
        I[Traefik Load Balancer]
        J[Automated Deployment]
    end
    
    A -.->|Migrate| E
    B -.->|Migrate| G
    C -.->|Migrate| H
    D -.->|Migrate| J
```

## 8. Benefits of Single Repository Approach

```mermaid
mindmap
  root((Single Repository Benefits))
    Maintenance
      Single Codebase
      Shared Bug Fixes
      Unified Updates
      Consistent Security
    Development
      Shared Components
      Code Reuse
      Faster Development
      Better Testing
    Deployment
      Separate Instances
      Study Isolation
      Independent Scaling
      Easy Rollbacks
    Migration
      Gradual Migration
      Risk Mitigation
      Data Preservation
      User Training
```
