# CNICS Validation Repository Multi-Study Setup Guide

> **Current implementation status (2026-05-08)**
>
> - **MCI and `scans` are implemented** (spec 012 wired the per-study
>   schema, model, and review-form layers). Worked examples for VTE,
>   CVA, Heart Failure, and AFIB describe how those studies *would* be
>   wired up when each is brought online; their per-study files are
>   dormant scaffolding. See `### 2. Study-Specific Components` below.
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
- **Patient Data**: All systems read patient identity via the `patients_view`
  view, which UNIONs the locally-owned `uw_patients2` table with a FEDERATED
  proxy of the upstream `cnics_data.Patient` table. Both halves are read-only
  from the application; new patient records originate upstream.
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
per-study artifacts in the repo. Since spec 012, each layer selects the
deployment's study by `STUDY_TYPE`. `mci` and `scans` are live. VTE, CVA,
HF, and AFIB files exist as **dormant** scaffolding, and a deployment of a
dormant (or unknown) study falls back to `mci` at every layer, with a
warning (constitution Principle IV, v1.5.1).

**Study-specific tables** (constitution Principle IV) are tables whose
shape depends on one study's data needs, following the legacy CakePHP
model: today `reviews` and `event_derived_datas`. A deployment's database
holds **only its own study's** study-specific tables. Every study must
define `reviews`, which always has `id`, `event_id`, `reviewer_id`, with
one row per reviewer per event. Shared code relies on those three columns
only. Other study-specific tables are defined only by the studies that use
them (e.g. `scans` has no `event_derived_datas`).

#### Database Schemas (`init/` directory)
```
init/
├── 01-create-db.sql               # Shared: creates the database.
├── 02-schema.sql                  # Shared tables only (users, events,
│                                  #   logs, criterias, ...).
├── 03-data.sql                    # Seed data.
├── 04-study-schema.sh             # Applies studies/<STUDY_TYPE>.sql —
│                                  #   and nothing else — on first start.
├── 05-add-indexes.sql             # Index creation.
├── 06-create-patients-view.sh     # Creates the FEDERATED proxy for
│                                  #   cnics_data.Patient and the
│                                  #   patients_view UNION view.
├── studies/                       # Study-specific DDL. NOT auto-run.
│   ├── mci.sql                    #   MI reviews + event_derived_datas.
│   ├── scans.sql                  #   scans reviews + review_regions.
│   └── vte.sql cva.sql hf.sql afib.sql
│                                  #   Dormant: first line `-- DORMANT`,
│                                  #   body wrapped in /* ... */.
└── migrations/                    # One-off upgrades for existing DBs
    └── 012-scans-reviews.sh       #   (existing scans deployments).
```

> **Why `studies/` is a subdirectory.** MariaDB runs every *top-level*
> file in `/docker-entrypoint-initdb.d/` (which `init/` is bind-mounted
> to) at first start, in every deployment, regardless of `STUDY_TYPE`.
> It does not descend into subdirectories. Study-specific DDL therefore
> lives in `init/studies/`, and `04-study-schema.sh` (which receives
> `STUDY_TYPE` via the mariadb service's environment) applies exactly one
> file. If the file is missing or begins with `-- DORMANT`, it applies
> `mci.sql` and prints a warning. `init/` runs only when a volume is first
> created, so existing databases are upgraded with scripts in
> `init/migrations/`.

#### Backend Models

Current layout in the repo:

```
flask_backend/
├── models/
│   ├── __init__.py                # Shared models + session handling
│   │                              #   (was models.py until spec 012).
│   └── studies/
│       ├── __init__.py            # get_study_module(): picks the
│       │                          #   module for STUDY_TYPE at call
│       │                          #   time; documents the interface.
│       ├── mci.py                 # MI reviews/event_derived_datas
│       │                          #   models + review form + export.
│       ├── scans.py               # scans reviews/review_regions
│       │                          #   models + review form + export.
│       └── vte.py cva.py hf.py afib.py
│                                  # Dormant: class definitions are
│                                  #   wrapped in a triple-quoted
│                                  #   string; no REVIEW_FORM export.
└── models2.py                     # Alternative SQLAlchemy variant
                                   #   (back_populates removed). Not
                                   #   currently imported — see the
                                   #   README "Alternative SQLAlchemy
                                   #   models" section.
```

A **study module** exports `Reviews`, `REVIEW_FORM` (the form id the
frontend renders, reported as `review_form` on `GET /api/config`),
`parse_review`, `add_review`, `get_reviews`, `export_csv`, and
`check_schema`. The shared review endpoints and the events export delegate
to it, so they contain no study names. Each study module declares its
**own** SQLAlchemy `DeclarativeBase`, because several studies define a
`reviews` table. Study models therefore have no ORM relationships to the
shared `Events`/`Users`, and joins are written explicitly. A module with
no `REVIEW_FORM` is dormant, and `get_study_module()` falls back to `mci`
with a warning.

#### Frontend Components

Current layout under `frontend/src/`:

```
frontend/src/
├── components/                    # Study-agnostic shared components.
├── pages/                         # Shared pages for every study.
└── studies/
    ├── mci/MciReviewForm.jsx      # Live: MI review form (spec 012).
    ├── scans/ScansReviewForm.jsx  # Live: scans review form (spec 012).
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

> **Study components (spec 012).** `studies/mci/MciReviewForm.jsx` and
> `studies/scans/ScansReviewForm.jsx` are live. They hold only the part
> of the review page that differs by study (Step 2 onwards) and are
> selected by `components/review/reviewForms.js`, keyed on `review_form`
> from `GET /api/config`. There is one page and one route. This is the
> pattern for new study-specific UI: a component chosen by a registry,
> not a copied page with parallel routes.

> **Routing gap (legacy VTE/CVA trees).** Apart from the review-form
> registry above, nothing under `frontend/src/` outside of `studies/`
> imports from `studies/`. The study-specific components
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

1. **Create or activate** the study's schema file,
   `init/studies/<study>.sql` (for VTE: `init/studies/vte.sql`, currently
   dormant). Define the study's `reviews` table, which must have `id`,
   `event_id`, `reviewer_id` with one row per reviewer per event, and any
   other study-specific table the study uses:
   ```sql
   -- Example for VTE study:
   `outcome` enum('Definite','Probable','No')
   `vte_type` enum('DVT','PE','Both','Other')
   `dvt_location` enum('Proximal','Distal','Upper','Other')
   `pe_severity` enum('Massive','Submassive','Low_risk')
   `imaging_evidence` tinyint(1)
   ```
2. **Remove** the `-- DORMANT` first line and the `/* ... */` wrapping.
   `init/04-study-schema.sh` then applies this file, and only this
   file, for `STUDY_TYPE=<study>` on a new volume.
3. **For existing databases**, add a one-off script under
   `init/migrations/` (see `012-scans-reviews.sh`), since `init/` only
   runs when a volume is first created.

### Step 3: Backend Model Configuration

1. **Implement** the study module, `flask_backend/models/studies/<study>.py`
   (for VTE: `vte.py`, currently dormant), following `scans.py`. It needs
   its own `DeclarativeBase`, a `Reviews` model, and the interface
   documented in `flask_backend/models/studies/__init__.py` (`REVIEW_FORM`,
   `parse_review`, `add_review`, `get_reviews`, `export_csv`,
   `check_schema`).
2. **Export `REVIEW_FORM`.** Its presence is what makes the module live:
   `get_study_module()` then returns it for `STUDY_TYPE=<study>` instead of
   falling back to `mci`.

### Step 4: Frontend Component Configuration

1. **Create** the study's review form component,
   `frontend/src/studies/<study>/<Study>ReviewForm.jsx`, taking
   `{ eventId, apiUrl }` and rendering Step 2 onwards of the review page.
2. **Register** it in `frontend/src/components/review/reviewForms.js`
   under the study module's `REVIEW_FORM` value.
3. Do **not** copy whole pages or add parallel routes (the legacy
   `studies/vte/` pattern). Per-study differences go in components
   chosen by a registry.

### Step 5: Study-Specific Assets

1. **Reviewer instructions** are an on-site, print-friendly HTML page
   (spec 012), not `.doc`/`.pdf` downloads. Add a self-contained
   `app/webroot/files/instructions/<study>.html` (see `scans.html`). It
   is served from the read-only `FILES_DIR` by `GET /files/...`.
2. **Link it** from the study's `instructions` entry in
   `frontend/src/components/reviewGuidance.js` with
   `{ label: 'View review instructions', href: '/files/instructions/<study>.html', download: false }`.
   Both the home page and the review page read that entry. A study with
   no entry shows no link.
3. Other per-study documents (packet assembly, scrubbing protocol) are
   linked from the same file's `packets` and `scrubbing` entries.

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

#### Database Schema (`init/studies/vte.sql`)
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

#### Database Schema (`init/studies/cva.sql`)
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

#### Database Schema (`init/studies/hf.sql`)
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

#### Database Schema (`init/studies/afib.sql`)
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

### Example: scans (Scan Validation) Study Deployment

`scans` is the canonical *selective-bypass* study (Constitution v1.4.0,
Principle V): it runs the lifecycle `created → uploaded → assigned →
reviewer1_done → done`, skipping scrubbing, screening, sending, and
second-/third-reviewer adjudication. It contrasts with the VTE example
above: VTE *extends* the workflow, whereas `scans` *removes* stages.
Since spec 012, `scans` also has its own review data (DEXA body regions
with T and Z scores), and so its own study-specific schema, study module,
and review form component.

Selecting `STUDY_TYPE=scans` supplies the bypass profile as the default;
the four workflow-stage controls are shown explicitly below for clarity.
An operator may still override any individual control.

#### Environment Configuration
```bash
STUDY_TYPE=scans
STUDY_NAME="Scan Validation"
STUDY_ABBREVIATION="SCANS"
ENABLE_SCRUBBING=false    # scans has nothing to scrub
ENABLE_SCREENING=false    # scans has nothing to screen
ENABLE_SENDING=false      # no separate dispatch step
REVIEWER_COUNT=1          # single-reviewer adjudication
```

#### Study-Specific Artifacts

- `init/studies/scans.sql`: `reviews` (one row per completed review) and
  `review_regions` (one row per body-region entry). There is no
  `event_derived_datas`, because a single reviewer has nothing to derive.
- `flask_backend/models/studies/scans.py`: models, validation, storage,
  retrieval, the per-region CSV export, and the schema check.
- `frontend/src/studies/scans/ScansReviewForm.jsx`: the review form.
- `app/webroot/files/instructions/scans.html`: the reviewer instructions,
  a print-friendly page opened in a new tab and editable on the server
  without a rebuild.
- `init/migrations/012-scans-reviews.sh`: a one-off upgrade for scans
  databases created before spec 012. It renames the old MI-shaped tables
  to `*_mi_pre012` and never drops them.

The bypassed states (`scrubbed`, `screened`, `sent`, `reviewer2_done`, and
the third-review states) remain defined in the shared schema and state
machine. A `scans` deployment simply never enters them ("bypass means
*not entered* for this deployment, not *deleted from the system*").

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
