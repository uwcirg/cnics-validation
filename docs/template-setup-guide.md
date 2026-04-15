# CNICS Validation Repository Multi-Study Setup Guide

## Overview

This repository is designed to support **multiple clinical validation studies** within the CNICS (Centers for AIDS Research Network of Integrated Clinical Systems) and NA-ACCORD frameworks. Currently configured for **Myocardial Infarction (MI) validation studies**, this guide explains how to deploy separate instances for other clinical studies while maintaining a single codebase.

## Supported Studies

### Active Studies (Migration from CakePHP)
- **Myocardial Infarction (MI)** - Currently implemented
- **VTE (Venothromboembolic)** - Includes prescrub step
- **CVA (Cerebrovascular Events - Stroke)** - Includes questionnaires
- **Heart Failure** - Currently in adjudication phase
- **AFIB** - No immediate plans, but code exists

### Novel Systems
- **Malignancy** - Does not use CakePHP, separate architecture

### Deprecated/Inactive Studies
- **CNICS COVID-19 Adjudication** - Never implemented
- **ESLD/ESRD (Chart Review)** - Last activity 2018
- **Lymphoma** - Last activity 2013

## Architecture Approach

Instead of forking or templating, this repository uses a **single codebase with separate deployments** approach:

- **Shared Core Components**: Common validation workflow, authentication, file handling
- **Study-Specific Configurations**: Database schemas, review criteria, UI customizations
- **Separate Deployments**: Each study gets its own containers, database, and domain
- **Maintainable Codebase**: Single repository for easier maintenance and updates

## Legacy CakePHP Migration Strategy

### Current State
The existing studies (MI, VTE, CVA, Heart Failure, AFIB) are currently running on **CakePHP v1.x** systems that were forked long ago and are in dire need of updating. These systems share largely identical code with disease-specific customizations.

### Migration Goals
1. **Consolidate Codebases**: Move from multiple forked CakePHP repositories to a single modern codebase
2. **Maintain Data Compatibility**: Ensure backwards compatibility with existing data
3. **Preserve Workflows**: Keep existing validation workflows intact
4. **Modernize Technology**: Move from CakePHP v1.x to modern React/Flask architecture

### Key Migration Considerations

#### Data Migration
- **Backwards Compatibility**: Existing tables must remain compatible or require migration scripts
- **Patient Data**: All systems use views on `cnics_data.Patients` table
- **Authentication**: Maintain existing Apache-edge auth contract — HTTP Basic Auth with `AuthBasicProvider ldap` and a `require ldap-group` rule (see repository-root [`.htaccess`](../.htaccess)), with the authenticated identity forwarded to the Flask backend as the `X-Remote-User` header. Keycloak is deferred to a later release and is NOT supported for first-release deployments.
- **Authorization**: Preserve simple role-based access (uploader_flag, admin_flag)

#### Study-Specific Differences
- **VTE**: Has prescrub step with "PSREJECTED" state
- **CVA**: Includes questionnaire functionality (uses dhair2 repo)
- **MI**: Standard workflow without prescrub
- **Heart Failure**: Currently in adjudication phase
- **AFIB**: No immediate plans for use

#### Technical Migration
- **Database**: Assume MariaDB compatibility
- **Authentication**: Apache edge auth via `.htaccess` using `AuthType basic` + `AuthBasicProvider ldap` (the repository-root [`.htaccess`](../.htaccess) is authoritative); Flask backend consumes `X-Remote-User` forwarded by Apache after a successful bind.
- **File Handling**: Preserve existing file upload/download workflows
- **API Compatibility**: Maintain existing API contracts where possible

## Current Architecture

The CNICS Validation system is built with:
- **Frontend**: React.js application with role-based access control
- **Backend**: Flask API with SQLAlchemy ORM
- **Database**: MariaDB/MySQL with study-specific schemas
- **Containerization**: Docker Compose for development and deployment
- **Authentication**: Two-layer contract — Apache edge basic auth with `AuthBasicProvider ldap` (per `.htaccess`) forwards the authenticated identity to the Flask backend as `X-Remote-User`; role flags (`admin`, `uploader`, `reviewer`, `third_reviewer`) are enforced in the backend via decorators. See the root README's Authentication and Authorization section and `.specify/memory/constitution.md` (Security & Data Governance → Authentication) for the full decision record.

## Study-Specific Configuration Strategy

### 1. Study Configuration System

Each study deployment will use environment variables and configuration files to customize behavior:

#### Environment-Based Configuration
```bash
# Study identification
STUDY_TYPE=mci  # or 'stroke', 'cancer', 'diabetes', etc.
STUDY_NAME="Myocardial Infarction"
STUDY_ABBREVIATION="MI"

# Study-specific database schema
DB_SCHEMA_VERSION=mci_v1  # or stroke_v1, etc.

# Study-specific features
ENABLE_PRE_SCRUB=false  # MCI doesn't use pre-scrub step
ENABLE_CARDIAC_INTERVENTIONS=true  # MCI-specific feature
```

#### Database Schema Selection
- **Shared Tables**: `users`, `events`, `logs` (core workflow)
- **Study-Specific Tables**: `reviews`, `event_derived_datas` (customized per study)
- **Schema Versioning**: Use different schema files for different studies

### 2. Study-Specific Components

#### Database Schemas (`init/` directory)
```
init/
├── 01-create-db.sql          # Shared
├── 02-schema-mci.sql         # MI-specific schema
├── 02-schema-stroke.sql      # Stroke-specific schema
├── 02-schema-cancer.sql      # Cancer-specific schema
└── 03-data-mci.sql          # MI-specific seed data
```

#### Backend Models (`flask_backend/models/`)
```
flask_backend/models/
├── base.py                   # Shared base models
├── mci.py                    # MI-specific models
├── stroke.py                 # Stroke-specific models
└── cancer.py                 # Cancer-specific models
```

#### Frontend Components (`frontend/src/studies/`)
```
frontend/src/studies/
├── mci/
│   ├── EventReview.jsx       # MI-specific review form
│   ├── Home.jsx              # MI-specific home page
│   └── instructions/         # MI-specific documentation
├── stroke/
│   ├── EventReview.jsx       # Stroke-specific review form
│   └── Home.jsx              # Stroke-specific home page
└── shared/                   # Shared components
```

### 3. Deployment Configuration

#### Docker Compose Overrides
Each study gets its own docker-compose override:

```yaml
# docker-compose.mci.yaml
version: '3.8'
services:
  backend:
    environment:
      - STUDY_TYPE=mci
      - DB_SCHEMA_VERSION=mci_v1
    volumes:
      - ./init/02-schema-mci.sql:/docker-entrypoint-initdb.d/02-schema.sql
      - ./studies/mci/instructions:/app/webroot/files

  frontend:
    environment:
      - VITE_STUDY_TYPE=mci
      - VITE_STUDY_NAME=Myocardial Infarction
```

#### Study-Specific Environment Files
```bash
# .env.mci
STUDY_TYPE=mci
STUDY_NAME="Myocardial Infarction"
DB_NAME=cnics_mci_validation
FRONTEND_ORIGIN=https://mci-validation.cirg.uw.edu
EMAIL_SUBJECT_PREFIX=CNICS / MI Validation

# .env.stroke  
STUDY_TYPE=stroke
STUDY_NAME="Stroke Validation"
DB_NAME=cnics_stroke_validation
FRONTEND_ORIGIN=https://stroke-validation.cirg.uw.edu
EMAIL_SUBJECT_PREFIX=CNICS / Stroke Validation
```

## Step-by-Step Multi-Study Deployment Process

### Step 1: Study Configuration Setup
1. **Create** study-specific environment file: `cp default.env .env.stroke`
2. **Configure** study parameters in the environment file:
   ```bash
   STUDY_TYPE=stroke
   STUDY_NAME="Stroke Validation"
   DB_NAME=cnics_stroke_validation
   FRONTEND_ORIGIN=https://stroke-validation.cirg.uw.edu
   ```
3. **Create** study-specific docker-compose override: `docker-compose.stroke.yaml`

### Step 2: Database Schema Creation
1. **Create** study-specific schema file: `cp init/02-schema.sql init/02-schema-stroke.sql`
2. **Modify** the schema for your study's requirements:
   ```sql
   -- Example for stroke study:
   `outcome` enum('Definite','Probable','Possible','No')
   `stroke_type` enum('Ischemic','Hemorrhagic','TIA','Other')
   `nihss_score` int(3)
   `imaging_evidence` tinyint(1)
   ```
3. **Update** docker-compose override to use the correct schema file

### Step 3: Backend Model Configuration
1. **Create** study-specific model file: `flask_backend/models/stroke.py`
2. **Implement** study-specific models extending base classes:
   ```python
   from .base import BaseReview, BaseEventDerivedData
   
   class StrokeReview(BaseReview):
       stroke_type = Column(Enum('Ischemic','Hemorrhagic','TIA','Other'))
       nihss_score = Column(Integer)
   ```
3. **Update** model factory to load correct models based on `STUDY_TYPE`

### Step 4: Frontend Component Configuration
1. **Create** study-specific component directory: `frontend/src/studies/stroke/`
2. **Copy** and customize study-specific components:
   - `EventReview.jsx` - Study-specific review form
   - `Home.jsx` - Study-specific home page and instructions
3. **Update** component routing to load study-specific components

### Step 5: Study-Specific Assets
1. **Create** study-specific instruction files: `studies/stroke/instructions/`
2. **Add** study-specific documentation:
   - Review packet assembly instructions
   - Reviewer guidelines
   - Study-specific forms and templates
3. **Update** docker-compose to mount study-specific files

### Step 6: Deployment Configuration
1. **Configure** domain and SSL certificates for the new study
2. **Set up** separate database instance
3. **Configure** authentication and user management
4. **Set up** monitoring and logging for the new deployment

### Step 7: Testing and Validation
1. **Deploy** the study-specific instance:
   ```bash
   docker-compose -f docker-compose.yaml -f docker-compose.stroke.yaml --env-file .env.stroke up
   ```
2. **Test** study-specific functionality
3. **Validate** data isolation between studies
4. **Test** user roles and permissions
5. **Verify** study-specific documentation and instructions

## Study-Specific Deployment Examples

### Example 1: VTE (Venothromboembolic) Study Deployment

#### Environment Configuration (`.env.vte`)
```bash
STUDY_TYPE=vte
STUDY_NAME="VTE Validation"
STUDY_ABBREVIATION="VTE"
DB_NAME=cnics_vte_validation
FRONTEND_ORIGIN=https://vte-validation.cirg.uw.edu
EMAIL_SUBJECT_PREFIX=CNICS / VTE Validation
ENABLE_PRE_SCRUB=true  # VTE has prescrub step
ENABLE_PRESCRUB_REJECTION=true  # VTE has PSREJECTED state
```

#### Database Schema (`init/02-schema-vte.sql`)
```sql
-- VTE-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','No') NOT NULL,
  `vte_type` enum('DVT','PE','Both','Other') DEFAULT NULL,
  `dvt_location` enum('Proximal','Distal','Upper','Other') DEFAULT NULL,
  `pe_severity` enum('Massive','Submassive','Low_risk') DEFAULT NULL,
  `imaging_evidence` tinyint(1) DEFAULT NULL,
  `anticoagulation` tinyint(1) DEFAULT NULL,
  `thrombophilia_workup` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
);

-- VTE-specific event statuses including prescrub
ALTER TABLE `events` MODIFY `status` enum('created','uploaded','prescrubbed','prescrub_rejected','scrubbed','screened','assigned','sent','reviewer1_done','reviewer2_done','third_review_needed','third_review_assigned','done','rejected','no_packet_available') NOT NULL DEFAULT 'created';
```

### Example 2: CVA (Cerebrovascular Events - Stroke) Study Deployment

#### Environment Configuration (`.env.cva`)
```bash
STUDY_TYPE=cva
STUDY_NAME="CVA Validation"
STUDY_ABBREVIATION="CVA"
DB_NAME=cnics_cva_validation
FRONTEND_ORIGIN=https://cva-validation.cirg.uw.edu
EMAIL_SUBJECT_PREFIX=CNICS / CVA Validation
ENABLE_QUESTIONNAIRES=true  # CVA has questionnaire functionality
ENABLE_SURVEY_MODULE=true
```

#### Database Schema (`init/02-schema-cva.sql`)
```sql
-- CVA-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','Possible','No') NOT NULL,
  `stroke_type` enum('Ischemic','Hemorrhagic','TIA','Other') DEFAULT NULL,
  `nihss_score` int(3) DEFAULT NULL,
  `imaging_evidence` tinyint(1) DEFAULT NULL,
  `time_to_treatment` int(4) DEFAULT NULL COMMENT 'minutes',
  `thrombolysis` tinyint(1) DEFAULT NULL,
  `mechanical_thrombectomy` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
);

-- CVA questionnaire tables
CREATE TABLE `questionnaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `questionnaire_type` enum('baseline','followup','outcome') NOT NULL,
  `completed_date` datetime DEFAULT NULL,
  `data` json DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

### Example 3: Heart Failure Study Deployment

#### Environment Configuration (`.env.hf`)
```bash
STUDY_TYPE=hf
STUDY_NAME="Heart Failure Validation"
STUDY_ABBREVIATION="HF"
DB_NAME=cnics_hf_validation
FRONTEND_ORIGIN=https://hf-validation.cirg.uw.edu
EMAIL_SUBJECT_PREFIX=CNICS / Heart Failure Validation
ENABLE_PRE_SCRUB=false
ENABLE_EF_MEASUREMENT=true
```

#### Database Schema (`init/02-schema-hf.sql`)
```sql
-- Heart Failure-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','Possible','No') NOT NULL,
  `hf_type` enum('HFrEF','HFpEF','HFmrEF','Unknown') DEFAULT NULL,
  `ejection_fraction` decimal(4,1) DEFAULT NULL,
  `nyha_class` enum('I','II','III','IV','Unknown') DEFAULT NULL,
  `bnp_level` int(6) DEFAULT NULL,
  `hospitalization_required` tinyint(1) DEFAULT NULL,
  `diuretic_use` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

### Example 4: AFIB Study Deployment

#### Environment Configuration (`.env.afib`)
```bash
STUDY_TYPE=afib
STUDY_NAME="AFIB Validation"
STUDY_ABBREVIATION="AFIB"
DB_NAME=cnics_afib_validation
FRONTEND_ORIGIN=https://afib-validation.cirg.uw.edu
EMAIL_SUBJECT_PREFIX=CNICS / AFIB Validation
ENABLE_PRE_SCRUB=false
STATUS=inactive  # No immediate plans per Heidi
```

#### Database Schema (`init/02-schema-afib.sql`)
```sql
-- AFIB-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','Possible','No') NOT NULL,
  `afib_type` enum('Paroxysmal','Persistent','Permanent','Unknown') DEFAULT NULL,
  `ecg_evidence` tinyint(1) DEFAULT NULL,
  `duration_hours` int(4) DEFAULT NULL,
  `anticoagulation` tinyint(1) DEFAULT NULL,
  `rate_control` tinyint(1) DEFAULT NULL,
  `rhythm_control` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

### Example 5: Malignancy Study Deployment

#### Environment Configuration (`.env.malignancy`)
```bash
STUDY_TYPE=malignancy
STUDY_NAME="Malignancy Validation"
STUDY_ABBREVIATION="MALIGNANCY"
DB_NAME=cnics_malignancy_validation
FRONTEND_ORIGIN=https://malignancy-validation.cirg.uw.edu
EMAIL_SUBJECT_PREFIX=CNICS / Malignancy Validation
ENABLE_PRE_SCRUB=false
ARCHITECTURE=novel  # Does not use CakePHP
```

#### Database Schema (`init/02-schema-malignancy.sql`)
```sql
-- Malignancy-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Confirmed','Suspected','No','Indeterminate') NOT NULL,
  `cancer_type` enum('Breast','Lung','Colorectal','Prostate','Lymphoma','Other') DEFAULT NULL,
  `stage` enum('I','II','III','IV','Unknown') DEFAULT NULL,
  `biopsy_confirmed` tinyint(1) DEFAULT NULL,
  `tumor_size` decimal(5,2) DEFAULT NULL,
  `metastasis` tinyint(1) DEFAULT NULL,
  `treatment_type` enum('Surgery','Chemotherapy','Radiation','Immunotherapy','Other') DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

## Best Practices for Multi-Study Deployments

### 1. Maintain Core Workflow
- Keep the fundamental validation workflow intact across all studies
- Preserve role-based access control (admin, uploader, reviewer)
- Maintain the event lifecycle (created → uploaded → scrubbed → reviewed → done)
- Ensure consistent API contracts across study deployments

### 2. Study Isolation
- **Separate Databases**: Each study gets its own database instance
- **Separate Domains**: Use study-specific subdomains (e.g., `mci-validation.cirg.uw.edu`)
- **Separate Containers**: Each deployment runs in isolated containers
- **Separate Authentication**: Study-specific user management and roles

### 3. Configuration Management
- Use environment variables for all study-specific settings
- Maintain separate environment files for each study
- Use docker-compose overrides for study-specific configurations
- Document all configuration options and their purposes

### 4. Code Organization
- Keep shared components in common directories
- Organize study-specific code in dedicated directories
- Use factory patterns to load study-specific models and components
- Maintain clear separation between shared and study-specific code

### 5. Database Design
- Use descriptive enum values that are clinically meaningful
- Include "Other" and "Unknown" options where appropriate
- Consider adding free-text fields for additional details
- Maintain referential integrity with foreign keys
- Version your database schemas for future migrations

### 6. Deployment Strategy
- Use consistent naming conventions for study deployments
- Implement automated deployment pipelines
- Use infrastructure as code for reproducible deployments
- Monitor each study deployment independently

## Deployment Commands

### Starting a Study Deployment
```bash
# For MCI study (currently implemented)
docker-compose -f docker-compose.yaml -f docker-compose.mci.yaml --env-file .env.mci up -d

# For VTE study (migration from CakePHP)
docker-compose -f docker-compose.yaml -f docker-compose.vte.yaml --env-file .env.vte up -d

# For CVA study (migration from CakePHP, includes questionnaires)
docker-compose -f docker-compose.yaml -f docker-compose.cva.yaml --env-file .env.cva up -d

# For Heart Failure study (migration from CakePHP)
docker-compose -f docker-compose.yaml -f docker-compose.hf.yaml --env-file .env.hf up -d

# For AFIB study (migration from CakePHP, currently inactive)
docker-compose -f docker-compose.yaml -f docker-compose.afib.yaml --env-file .env.afib up -d

# For Malignancy study (novel system, separate architecture)
docker-compose -f docker-compose.yaml -f docker-compose.malignancy.yaml --env-file .env.malignancy up -d
```

### Managing Multiple Studies
```bash
# List running containers for all studies
docker ps --filter "name=cnics"

# View logs for specific study
docker-compose -f docker-compose.yaml -f docker-compose.vte.yaml logs -f

# Stop specific study deployment
docker-compose -f docker-compose.yaml -f docker-compose.vte.yaml down

# Restart all studies
docker-compose -f docker-compose.yaml -f docker-compose.mci.yaml --env-file .env.mci restart
docker-compose -f docker-compose.yaml -f docker-compose.vte.yaml --env-file .env.vte restart
docker-compose -f docker-compose.yaml -f docker-compose.cva.yaml --env-file .env.cva restart
docker-compose -f docker-compose.yaml -f docker-compose.hf.yaml --env-file .env.hf restart
```

## Monitoring and Maintenance

### Study-Specific Monitoring
- Set up separate monitoring dashboards for each study
- Use study-specific log aggregation
- Monitor study-specific metrics and KPIs
- Implement study-specific alerting

### Updates and Patches
- Test updates in development environment first
- Apply updates to all study deployments consistently
- Maintain backward compatibility where possible
- Document any breaking changes and migration steps

### Data Management
- Implement study-specific backup strategies
- Use separate backup schedules for each study
- Maintain data retention policies per study requirements
- Ensure compliance with study-specific data governance

## Security Considerations

### Study Data Isolation
- Ensure complete data separation between studies
- Use separate database users and permissions
- Implement study-specific access controls
- Regular security audits for each deployment

### Authentication and Authorization
- Maintain separate user databases for each study
- Implement study-specific role definitions
- Use consistent authentication mechanisms
- Regular access reviews and user management

## Conclusion

This multi-study deployment approach allows you to leverage the robust CNICS validation framework while maintaining complete separation between different clinical studies. Each study gets its own isolated deployment with study-specific configurations, while sharing the core validation workflow and security features.

The single codebase approach makes maintenance much easier than forking or templating, while the separate deployments ensure complete data isolation and study-specific customization.

For questions or support with multi-study deployments, refer to the CNICS development team or create an issue in the repository.
