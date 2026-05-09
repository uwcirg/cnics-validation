# CNICS Validation Repository Multi-Study Setup Guide

> **Current implementation status (2026-05-08)**
>
> - **Only MCI is implemented.** Worked examples for VTE, CVA, Heart
>   Failure, and AFIB describe how those studies *would* be wired up
>   when each is brought online; their per-study schema files, models,
>   and frontend component directories are scaffolding-only or absent.
>   See `### 2. Study-Specific Components` below for the per-layer
>   reachability/wiring gaps.
> - **One `.env`, one `docker-compose.yaml`** (Constitution Principle
>   IV, v1.2.0). To target a specific study, edit the `.env` in your
>   deployment directory: set `STUDY_TYPE`, study identity, and any
>   feature flags. Side-by-side deployments on the same host are
>   differentiated via `COMPOSE_PROJECT_NAME`, not filename suffixes.
>   Per-study overlay files (`.env.<study>`, `docker-compose.<study>.yaml`)
>   and `--env-file` / `-f` flag combinations that select among them
>   are not a permitted pattern.
> - **Non-edge services bind to `127.0.0.1`** (Constitution Security &
>   Data Governance → Network exposure boundaries, v1.3.0). Both `web`
>   and `backend` are bound to the loopback interface in
>   `docker-compose.yaml`; external traffic must traverse the Apache
>   `.htaccess` edge that performs basic+ldap auth and forwards
>   `X-Remote-User`.
>
> See `.specify/memory/constitution.md` for normative scope; this
> guide is the operational *how*.

## Overview

This repository is designed to support **multiple clinical validation studies** within the CNICS (Centers for AIDS Research Network of Integrated Clinical Systems) and NA-ACCORD frameworks. Currently configured for **Myocardial Infarction (MI) validation studies**, this guide explains how to deploy separate instances for other clinical studies while maintaining a single codebase.

## Supported Studies

### Active Studies (Migration from CakePHP)
- **Myocardial Infarction (MI)** - Currently implemented
- **VTE (Venothromboembolic)** - Includes prescrub step
- **CVA (Cerebrovascular Events - Stroke)** - Includes questionnaires
- **Heart Failure** - Currently in adjudication phase
- **AFIB** - No immediate plans, but code exists

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
STUDY_TYPE=mci  # or 'vte', 'cva', 'hf', 'afib'
STUDY_NAME="Myocardial Infarction"
STUDY_ABBREVIATION="MI"

# Study-specific database schema
DB_SCHEMA_VERSION=mci_v1  # or vte_v1, etc.

# Study-specific features
ENABLE_PRE_SCRUB=false  # MCI doesn't use pre-scrub step
ENABLE_CARDIAC_INTERVENTIONS=true  # MCI-specific feature
```

#### Database Schema Selection
- **Shared Tables**: `users`, `events`, `logs` (core workflow)
- **Study-Specific Tables**: `reviews`, `event_derived_datas` (customized per study)
- **Schema Versioning**: Use different schema files for different studies

### 2. Study-Specific Components

The three sub-sections below describe the **actual current state** of
per-study artifacts in the repo. As of the first release, only the
default (MCI) layer is loaded by the running stack; the per-study
files for VTE, CVA, HF, and AFIB exist as scaffolding in varying
degrees of completeness but are not yet wired into the runtime.
Bringing any of them online requires the runtime selection work
flagged in the implementation-status banner at the top of this guide,
plus removing the per-file "commented out until needed" gates noted
below.

#### Database Schemas (`init/` directory)
```
init/
├── 01-create-db.sql               # Shared: creates the database.
├── 02-schema.sql                  # Default schema (MCI). The only
│                                  #   schema actually applied today.
├── 02-schema-<study>.sql          # Scaffolding for vte, cva, hf, afib.
│                                  #   Each is entirely wrapped in a
│                                  #   /* ... */ block comment, so it
│                                  #   is inert when MariaDB processes
│                                  #   it at first start.
├── 03-data.sql                    # Seed data.
├── 04-create-patients.sql         # Patient table population.
└── 05-add-indexes.sql             # Index creation.
```

> **Wiring gap.** MariaDB processes everything in
> `/docker-entrypoint-initdb.d/` (which `init/` is bind-mounted to)
> at first start. The per-study files are inert today only because
> they're wrapped in SQL block comments. Bringing per-study schemas
> online requires (a) uncommenting the relevant file *and* (b)
> ensuring only the right `02-schema-<STUDY_TYPE>.sql` runs — e.g.,
> an entrypoint that picks the right file at startup. Uncommenting
> all of them would collide on the `reviews` table definition.

#### Backend Models

Current layout in the repo:

```
flask_backend/
├── models.py                      # Active: contains the MCI models
│                                  #   loaded by the running stack
│                                  #   (~15 KB, single file).
├── models2.py                     # Alternative SQLAlchemy variant
│                                  #   (back_populates removed). Not
│                                  #   currently imported — see the
│                                  #   README "Alternative SQLAlchemy
│                                  #   models" section.
└── models/
    └── studies/                   # Per-study scaffolding files
                                   #   (afib.py, cva.py, hf.py,
                                   #   vte.py). Each file's class
                                   #   definitions are wrapped in a
                                   #   triple-quoted string, so the
                                   #   file would import as a no-op
                                   #   even if it were reachable.
```

> **Reachability gap.** `flask_backend/models/studies/*.py` files
> exist on disk but are **not currently importable** as Python
> modules. The file `flask_backend/models.py` is found before the
> directory `flask_backend/models/` during import resolution, so
> `from flask_backend.models.studies import vte` raises
> `ModuleNotFoundError: 'flask_backend.models' is not a package`.
> Bringing per-study models online (Step 3 of the deployment
> walkthrough) therefore requires:
>
> 1. Consolidating `models.py` into the `models/` package — e.g.,
>    move its contents into `models/__init__.py` (or split into
>    `models/base.py`, `models/mci.py`, etc.) and delete the
>    standalone `models.py`. Constitution Principle IV item 3
>    prescribes the package layout (`flask_backend/models/<study>.py`).
> 2. Removing the triple-quoted-string gates inside each per-study
>    file so the class definitions actually execute.

#### Frontend Components

Current layout under `frontend/src/`:

```
frontend/src/
├── components/                    # Study-agnostic shared components.
├── pages/                         # Currently holds the MCI flow.
│                                  #   No `studies/mci/` subdirectory
│                                  #   exists; MCI is the default.
└── studies/
    ├── cva/                       # CVA scaffolding (1 file:
    │                              #   EventReview.jsx, wrapped in a
    │                              #   /* ... */ gate).
    └── vte/                       # VTE scaffolding (~24 files
                                   #   including Admin.jsx,
                                   #   EventReview.jsx, Home.jsx,
                                   #   plus many other routes —
                                   #   these are real React
                                   #   components, not commented-out
                                   #   shells).
```

> **Routing gap.** Nothing under `frontend/src/` outside of
> `studies/` imports from `studies/`. The study-specific components
> are not loaded by the frontend router. Bringing them online
> requires extending the router to dispatch on a study selector
> (e.g., a `VITE_STUDY_TYPE` env var, mirroring the backend's
> `STUDY_TYPE`) before mounting the per-study component tree. Note
> the asymmetry: the VTE tree is real, executable code awaiting a
> caller, while the CVA tree is a commented-out shell.

### 3. Deployment Configuration

Each study deployment is a single directory containing:

- A copy of the repository (`git clone` or worktree).
- A single `.env` file at the repo root, copied from `default.env`
  and edited for the target study.
- The unmodified canonical `docker-compose.yaml` (no per-study
  overlays — Constitution Principle IV, v1.2.0).

**To target a study**, edit `.env` to set:

- `STUDY_TYPE` — the study selector (e.g., `mci`, `vte`, `cva`,
  `hf`, `afib`); see Supported Studies above for the canonical list.
- `STUDY_NAME` and `STUDY_ABBREVIATION` — display strings used in
  emails, page titles, and logs.
- Per-study feature flags — see "Study-Specific Deployment Examples"
  below for which flags each study expects.
- Deployment-specific values — `DB_*`, `FRONTEND_ORIGIN`,
  `SERVER_NAME`, SMTP/email config — chosen by the operator for
  the target host.

**To run multiple deployments side-by-side on one host**, give each
deployment a distinct `COMPOSE_PROJECT_NAME` in its `.env`. That value
namespaces the deployment's containers, network, and named volumes
so two studies on the same VM don't collide. Distinct host port
bindings (`EXTERNAL_PORT`, etc.) are also required when ports
overlap.

## Step-by-Step Multi-Study Deployment Process

The walkthrough below uses **VTE** as the worked example for adding a
new study to the codebase. The process touches two distinct layers:
study scaffolding (Steps 1–5, modifying shared code or adding
study-specific files in the repo) and the per-host deployment
(Steps 6–7, using `.env` to target a specific study and host).

> **Implementation note.** Steps 2–5 describe per-study artifacts
> (schema files, models, components, assets) and the runtime
> selection logic that loads them based on `STUDY_TYPE`. As of the
> first release these are **scaffolding-only or absent** for any
> study other than MCI — the banner at the top of this guide
> explains the gap. Treat Steps 2–5 as documentation of what *would*
> be required to bring a new study online, not as already-functional
> recipes.

### Step 1: Study Configuration Setup

1. **Copy** the canonical env template into the deployment
   directory's `.env`:
   ```bash
   cp default.env .env
   ```
2. **Edit** `.env` to set the study-targeting variables:
   ```bash
   STUDY_TYPE=vte
   STUDY_NAME="VTE Validation"
   STUDY_ABBREVIATION="VTE"
   ENABLE_PRE_SCRUB=true
   ENABLE_PRESCRUB_REJECTION=true
   ```
   (See "Study-Specific Deployment Examples" below for the full set
   of overrides each study expects.)
3. **Set deployment-specific values** in the same `.env` —
   `COMPOSE_PROJECT_NAME` (required if running side-by-side with
   another deployment on this host), `DB_*`, `FRONTEND_ORIGIN`,
   `SERVER_NAME`, SMTP/email config.

### Step 2: Database Schema Creation

1. **Create** the study-specific schema file:
   ```bash
   cp init/02-schema.sql init/02-schema-vte.sql
   ```
2. **Modify** the schema for the study's requirements:
   ```sql
   -- Example for VTE study:
   `outcome` enum('Definite','Probable','No')
   `vte_type` enum('DVT','PE','Both','Other')
   `dvt_location` enum('Proximal','Distal','Upper','Other')
   `pe_severity` enum('Massive','Submassive','Low_risk')
   `imaging_evidence` tinyint(1)
   ```
3. **Wire schema selection into runtime** — since per-study compose
   overlays are not permitted (Constitution Principle IV), the
   running container must pick the correct `02-schema-<study>.sql`
   based on `STUDY_TYPE` at startup (e.g., an entrypoint that copies
   the right file into the MariaDB init directory before mariadb
   starts). *Currently scaffolding-only.*

### Step 3: Backend Model Configuration

1. **Create** the study-specific model file:
   `flask_backend/models/studies/vte.py`
2. **Implement** study-specific models extending base classes:
   ```python
   from .base import BaseReview, BaseEventDerivedData

   class VTEReview(BaseReview):
       vte_type = Column(Enum('DVT','PE','Both','Other'))
       imaging_evidence = Column(Boolean)
   ```
3. **Update the model factory** (currently
   `flask_backend/study_config.py` — scaffolding-only, not imported
   by any runtime code) to load the correct module based on
   `STUDY_TYPE`.

### Step 4: Frontend Component Configuration

1. **Create** the study-specific component directory:
   `frontend/src/studies/vte/`
2. **Copy** and customize study-specific components:
   - `EventReview.jsx` — study-specific review form
   - `Home.jsx` — study-specific home page and instructions
3. **Update** component routing to load the correct study's
   components based on the study selector. *Selection mechanism
   not yet implemented.*

### Step 5: Study-Specific Assets

1. **Create** the study-specific instruction directory:
   `studies/vte/instructions/`
2. **Add** study-specific documentation:
   - Review packet assembly instructions
   - Reviewer guidelines
   - Study-specific forms and templates
3. **Wire asset selection into runtime** — like the schema in
   Step 2, the running container must pick the correct per-study
   assets based on `STUDY_TYPE`. Compose-time bind-mount selection
   is not permitted. *Currently scaffolding-only.*

### Step 6: Deployment Configuration

1. **Choose** the deployment domain and configure SSL certificates.
2. **Provision** the database (the bundled MariaDB container will
   create the schema named in `DB_NAME` on first start; for an
   external DB, point `DB_HOST` at it).
3. **Configure** authentication: confirm the host's Apache/`.htaccess`
   forwards `X-Remote-User` from a successful basic+ldap bind (see
   `.specify/memory/constitution.md` Security & Data Governance →
   Authentication).
4. **Set distinct `COMPOSE_PROJECT_NAME` and host ports** if this
   deployment will run on a host that already runs another
   deployment of this stack.

### Step 7: Testing and Validation

1. **Deploy** the study instance from the deployment directory:
   ```bash
   docker compose up -d
   ```
2. **Test** study-specific functionality (review form fields,
   feature-flag-gated behavior such as VTE's prescrub step, etc.).
3. **Validate** data isolation against any other deployments on
   the same host (separate DB, separate domain, distinct
   `COMPOSE_PROJECT_NAME`).
4. **Test** user roles and permissions.
5. **Verify** study-specific documentation, assets, and
   instructional files appear correctly.

## Study-Specific Deployment Examples

Each block below shows only the variables that are **intrinsic to the
study** — the study selector, study identity, and per-study feature
flags. The rest of the env file (DB credentials, domain, SMTP, email
prefix, logging, file paths, etc.) is deployment-specific and lives in
the canonical template (`default.env`). To deploy a given study, copy
`default.env` to `.env`, edit the deployment-specific values for your
host, and add the study-specific overrides shown here.

### Example: MCI (Myocardial Infarction) Study Deployment

This is the only study supported by the first release.

#### Environment Configuration
```bash
STUDY_TYPE=mci
STUDY_NAME="Myocardial Infarction"
STUDY_ABBREVIATION="MI"
ENABLE_CARDIAC_INTERVENTIONS=true
```

### Example: VTE (Venothromboembolic) Study Deployment

#### Environment Configuration
```bash
STUDY_TYPE=vte
STUDY_NAME="VTE Validation"
STUDY_ABBREVIATION="VTE"
ENABLE_PRE_SCRUB=true            # VTE has prescrub step
ENABLE_PRESCRUB_REJECTION=true   # VTE has PSREJECTED state
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

### Example: CVA (Cerebrovascular Events - Stroke) Study Deployment

#### Environment Configuration
```bash
STUDY_TYPE=cva
STUDY_NAME="CVA Validation"
STUDY_ABBREVIATION="CVA"
ENABLE_QUESTIONNAIRES=true       # CVA has questionnaire functionality
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

### Example: Heart Failure Study Deployment

#### Environment Configuration
```bash
STUDY_TYPE=hf
STUDY_NAME="Heart Failure Validation"
STUDY_ABBREVIATION="HF"
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

### Example: AFIB Study Deployment

#### Environment Configuration
```bash
STUDY_TYPE=afib
STUDY_NAME="AFIB Validation"
STUDY_ABBREVIATION="AFIB"
ENABLE_PRE_SCRUB=false
STATUS=inactive                  # No immediate plans per Heidi
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
- Each deployment uses a single `.env` (no per-study overlay files)
  and a single canonical `docker-compose.yaml` (no overrides) — see
  Constitution Principle IV
- Differentiate side-by-side deployments via `COMPOSE_PROJECT_NAME`
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

A deployment's commands are study-agnostic: every deployment uses the
same canonical `docker-compose.yaml` and reads its single `.env` file
from the deployment directory. The study being deployed is determined
by what `STUDY_TYPE` (and related variables) are set to in `.env`.

### Starting, Stopping, and Restarting

Run from the deployment directory (with `.env` configured for the
target study and host):

```bash
docker compose up -d
docker compose down
docker compose restart
```

### Viewing Logs

```bash
docker compose logs -f
docker compose logs -f backend       # specific service
```

### Listing Running Containers

```bash
docker compose ps                    # only this deployment
docker ps --filter "name=cnics-"     # any deployment whose
                                     # COMPOSE_PROJECT_NAME starts
                                     # with "cnics-"
```

### Side-by-Side Multiple Deployments

Two deployments of this stack on the same host MUST live in distinct
directories, each with its own `.env`. Differentiate them by:

1. **`COMPOSE_PROJECT_NAME`** — set distinct values in each `.env`
   (e.g., `cnics-mci`, `cnics-vte`). This namespaces each
   deployment's containers, network, and named volumes so the two
   stacks don't collide.
2. **Host ports** — set distinct `EXTERNAL_PORT` (web/frontend
   service) and `BACKEND_EXTERNAL_PORT` (Flask API service) in
   each `.env`. Two containers cannot bind the same host port.

Then run `docker compose up -d` from each deployment directory
independently. The two stacks are fully isolated; commands run from
deployment A's directory operate only on deployment A's containers.

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
