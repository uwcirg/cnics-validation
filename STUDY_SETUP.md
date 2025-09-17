# Study-Specific Setup Guide

This repository contains pre-configured, commented-out code for all CNICS validation studies. This guide explains how to activate and deploy each study.

## Available Studies

### Active Studies (Ready for Migration)
- **VTE (Venothromboembolic)** - Includes prescrub step with PSREJECTED state
- **CVA (Cerebrovascular Events - Stroke)** - Includes questionnaire functionality
- **Heart Failure** - Currently in adjudication phase
- **AFIB** - No immediate plans, but code exists
- **Malignancy** - Novel system, separate architecture

### Currently Active
- **MCI (Myocardial Infarction)** - Currently implemented and running

## File Structure

```
cnics-validation/
├── init/
│   ├── 02-schema.sql              # Current MCI schema (active)
│   ├── 02-schema-vte.sql          # VTE schema (commented out)
│   ├── 02-schema-cva.sql          # CVA schema (commented out)
│   ├── 02-schema-hf.sql           # Heart Failure schema (commented out)
│   ├── 02-schema-afib.sql         # AFIB schema (commented out)
│   └── 02-schema-malignancy.sql   # Malignancy schema (commented out)
├── flask_backend/
│   ├── models/
│   │   ├── models.py              # Current MCI models (active)
│   │   └── studies/
│   │       ├── vte.py             # VTE models (commented out)
│   │       ├── cva.py             # CVA models (commented out)
│   │       ├── hf.py              # Heart Failure models (commented out)
│   │       ├── afib.py            # AFIB models (commented out)
│   │       └── malignancy.py      # Malignancy models (commented out)
│   └── study_config.py            # Study configuration factory
├── frontend/src/studies/
│   ├── vte/
│   │   └── EventReview.jsx        # VTE review component (commented out)
│   ├── cva/
│   │   └── EventReview.jsx        # CVA review component (commented out)
│   ├── hf/                        # Heart Failure components (commented out)
│   ├── afib/                      # AFIB components (commented out)
│   └── malignancy/                # Malignancy components (commented out)
├── studies/
│   ├── vte/instructions/          # VTE-specific documentation
│   ├── cva/instructions/          # CVA-specific documentation
│   ├── hf/instructions/           # Heart Failure documentation
│   ├── afib/instructions/         # AFIB documentation
│   └── malignancy/instructions/   # Malignancy documentation
├── env.vte.example                # VTE environment template
├── env.cva.example                # CVA environment template
├── env.hf.example                 # Heart Failure environment template
├── env.afib.example               # AFIB environment template
├── env.malignancy.example         # Malignancy environment template
├── docker-compose.vte.yaml        # VTE docker override (commented out)
├── docker-compose.cva.yaml        # CVA docker override (commented out)
├── docker-compose.hf.yaml         # Heart Failure docker override (commented out)
├── docker-compose.afib.yaml       # AFIB docker override (commented out)
└── docker-compose.malignancy.yaml # Malignancy docker override (commented out)
```

## How to Activate a Study

### Step 1: Choose Your Study
Decide which study you want to deploy (e.g., VTE, CVA, Heart Failure, etc.).

### Step 2: Uncomment Database Schema
1. Navigate to `init/02-schema-[study].sql`
2. Remove the `/*` and `*/` comment markers
3. Review and modify the schema as needed for your specific requirements

### Step 3: Uncomment Backend Models
1. Navigate to `flask_backend/models/studies/[study].py`
2. Remove the `"""` comment markers
3. Update the `study_config.py` to include your study in the active configs

### Step 4: Uncomment Frontend Components
1. Navigate to `frontend/src/studies/[study]/EventReview.jsx`
2. Remove the `/*` and `*/` comment markers
3. Update the main App.jsx to include the new study routes

### Step 5: Create Environment File
1. Copy `env.[study].example` to `.env.[study]`
2. Uncomment and configure all the environment variables
3. Set appropriate database names, domains, and study-specific settings

### Step 6: Uncomment Docker Compose Override
1. Navigate to `docker-compose.[study].yaml`
2. Remove the `#` comment markers
3. Update domain names and database configurations

### Step 7: Deploy the Study
```bash
# Deploy the new study
docker-compose -f docker-compose.yaml -f docker-compose.[study].yaml --env-file .env.[study] up -d
```

## Study-Specific Features

### VTE (Venothromboembolic)
- **Prescrub Step**: Includes prescrub workflow with PSREJECTED state
- **VTE-Specific Fields**: DVT location, PE severity, anticoagulation, thrombophilia workup
- **Status Workflow**: `prescrubbed` → `prescrub_rejected` states

### CVA (Cerebrovascular Events - Stroke)
- **Questionnaire Functionality**: Includes questionnaire tables and components
- **CVA-Specific Fields**: NIHSS score, stroke type, thrombolysis, mechanical thrombectomy
- **Survey Module**: Baseline, followup, outcome questionnaires

### Heart Failure
- **HF-Specific Fields**: Ejection fraction, NYHA class, BNP levels, HF type
- **Status**: Currently in adjudication phase

### AFIB
- **AFIB-Specific Fields**: AFIB type, duration, rate/rhythm control, CHADS2 scores
- **Status**: No immediate plans for use

### Malignancy
- **Novel Architecture**: Does not use CakePHP
- **Cancer-Specific Fields**: Cancer type, stage, biopsy confirmation, tumor size

## Configuration Examples

### Environment Variables
```bash
# Study identification
STUDY_TYPE=vte
STUDY_NAME="VTE Validation"
STUDY_ABBREVIATION="VTE"

# Study-specific features
ENABLE_PRE_SCRUB=true
ENABLE_PRESCRUB_REJECTION=true
```

### Docker Compose Override
```yaml
services:
  backend:
    environment:
      - STUDY_TYPE=vte
      - ENABLE_PRE_SCRUB=true
    volumes:
      - ./init/02-schema-vte.sql:/docker-entrypoint-initdb.d/02-schema.sql
```

## Migration from CakePHP

When migrating from existing CakePHP systems:

1. **Export Data**: Export existing data from CakePHP databases
2. **Map Fields**: Map old CakePHP fields to new schema
3. **Test Migration**: Test data migration scripts
4. **Validate**: Ensure all study-specific workflows work correctly

## Best Practices

1. **Test First**: Always test in development environment before production
2. **Backup Data**: Backup existing data before migration
3. **Document Changes**: Document any customizations made to the base code
4. **Monitor Logs**: Monitor application logs during and after deployment
5. **User Training**: Provide training for users on new study-specific features

## Support

For questions or issues with study setup:
1. Check the main documentation in `docs/template-setup-guide.md`
2. Review study-specific comments in the code files
3. Contact the CNICS development team
4. Create an issue in the repository
