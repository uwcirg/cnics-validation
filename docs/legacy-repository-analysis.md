# Legacy Repository Analysis and Migration Requirements

## Executive Summary

After examining the old CakePHP repositories (VTE, Heart Failure, and AFIB), I've identified key differences and requirements that need to be incorporated into our multi-study architecture plan. The analysis reveals that our current plan is largely on track, but several important details need to be updated.

## Repository Status

### AFIB Repository
- **Status**: Complete CakePHP v1.x application
- **Key Finding**: **NO PRESCRUB WORKFLOW** - AFIB follows standard workflow like MCI and HF
- **Database**: Uses same core tables as MCI but with AFIB-specific review fields
- **Note**: Per your boss's notes, AFIB has "no plans to use for the foreseeable future"
- **Action**: Keep our commented-out AFIB code as placeholder for future use

### VTE Repository
- **Status**: Complete CakePHP v1.x application
- **Key Finding**: **CONFIRMS PRESCRUB WORKFLOW** - VTE has a dedicated prescrub step
- **Database**: Uses same core tables as MCI but with VTE-specific review fields

### Heart Failure Repository  
- **Status**: Complete CakePHP v1.x application
- **Key Finding**: **NO PRESCRUB WORKFLOW** - HF follows standard workflow like MCI
- **Database**: Uses same core tables as MCI but with HF-specific review fields

## Key Findings and Required Updates

### 1. VTE Prescrub Workflow Confirmation

**What We Found:**
- VTE has a dedicated `prescrub.ctp` view file
- VTE event model includes `prescrubber_id` foreign key
- VTE has `PSREJECTED` status constant
- VTE workflow: `created` → `uploaded` → `prescrubbed` → `psrejected` (if failed) → `scrubbed` → etc.

**Our Current Plan Status:** ✅ **CORRECT** - We already included prescrub workflow in our VTE schema

**Required Updates:**
- ✅ Already included in `init/02-schema-vte.sql`
- ✅ Already included in `flask_backend/models/studies/vte.py`
- ✅ Already included in environment configs

### 2. Heart Failure Workflow Confirmation

**What We Found:**
- HF event model does NOT include `prescrubber_id` foreign key
- HF does NOT have `PSREJECTED` status constant
- HF workflow: `created` → `uploaded` → `scrubbed` → `screened` → etc. (same as MCI)

**Our Current Plan Status:** ✅ **CORRECT** - We already excluded prescrub from HF

**Required Updates:**
- ✅ Already correct in `init/02-schema-hf.sql`
- ✅ Already correct in `flask_backend/models/studies/hf.py`

### 3. VTE Review Form Complexity

**What We Found:**
- VTE has extremely complex review form with 1000+ lines of JavaScript
- VTE review includes:
  - PE (Pulmonary Embolism) flags and subtypes
  - DVT (Deep Vein Thrombosis) flags and locations
  - Catheter-induced thrombosis subtypes
  - Complex conditional logic for form validation
  - Risk factors (malignancy, surgery, immobility, etc.)
  - Management details (anticoagulation, duration, etc.)

**Our Current Plan Status:** ⚠️ **NEEDS ENHANCEMENT** - Our VTE review form is too simplified

**Required Updates:**
- Update `frontend/src/studies/vte/EventReview.jsx` with full VTE complexity
- Add all VTE-specific fields from the CakePHP form
- Implement conditional form logic
- Add proper validation rules

### 4. Heart Failure Review Form

**What We Found:**
- HF review includes:
  - LVEF (Left Ventricular Ejection Fraction) with range 1-80%
  - HF classification (Preserved, Intermediate, Reduced)
  - Congestion assessment
  - Lab values
  - Presentation types (Left-sided, Right-sided, Combined)

**Our Current Plan Status:** ⚠️ **NEEDS ENHANCEMENT** - Our HF review form needs more detail

**Required Updates:**
- Update `frontend/src/studies/hf/EventReview.jsx` with HF-specific fields
- Add LVEF range validation
- Add HF classification options
- Add congestion assessment fields

### 5. AFIB Review Form Complexity

**What We Found:**
- AFIB has complex review form with 1000+ lines of JavaScript
- AFIB review includes:
  - AFIB/AFlutter flags and encounter types
  - AF timing (Presented in AF vs AF started after admission)
  - AF type classification (Paroxysmal, Persistent, Permanent)
  - Associated conditions (Coronary, MI, HF, VHD, COPD, Stroke, etc.)
  - Substance use (Smoking, Heavy Alcohol, Other substances)
  - Secondary causes assessment
  - Echocardiogram findings
  - Anticoagulation management

**Our Current Plan Status:** ⚠️ **NEEDS ENHANCEMENT** - Our AFIB review form is too simplified

**Required Updates:**
- Update `frontend/src/studies/afib/EventReview.jsx` with full AFIB complexity
- Add all AFIB-specific fields from the CakePHP form
- Implement conditional form logic
- Add proper validation rules

### 6. Database Schema Differences

**What We Found:**
- All studies use the same core table structure (`events`, `reviews`, `users`, etc.)
- Differences are in the `reviews` table fields and enum values
- VTE has additional status workflow states (prescrub)
- AFIB and HF follow standard workflow (no prescrub)
- Each study has study-specific constants and validation rules

**Our Current Plan Status:** ✅ **CORRECT** - Our schema approach is right

**Required Updates:**
- ✅ Database schema separation is correct
- ✅ Study-specific models approach is correct

## Detailed Migration Requirements

### VTE Migration Requirements

#### Database Schema Updates Needed:
```sql
-- Add missing VTE-specific fields to reviews table
ALTER TABLE reviews ADD COLUMN pe_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN dvt_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN cat_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN no_vte_flag TINYINT(1);
-- ... (many more fields from the CakePHP form)
```

#### Frontend Updates Needed:
- Complete rewrite of VTE EventReview component
- Add complex conditional form logic
- Add all VTE-specific form fields
- Implement proper validation

#### Backend Updates Needed:
- Add VTE-specific validation rules
- Add prescrub workflow logic
- Add VTE-specific constants

### Heart Failure Migration Requirements

#### Database Schema Updates Needed:
```sql
-- Add missing HF-specific fields to reviews table
ALTER TABLE reviews ADD COLUMN lvef INT(3);
ALTER TABLE reviews ADD COLUMN congestion ENUM('Yes', 'No', 'N/A - No Xray');
ALTER TABLE reviews ADD COLUMN hf_type ENUM('Not HF', 'Probable HF', 'Definite HF', 'Definite/Probable HF');
-- ... (more HF-specific fields)
```

#### Frontend Updates Needed:
- Enhance HF EventReview component
- Add LVEF range input (1-80%)
- Add HF classification options
- Add congestion assessment

#### Backend Updates Needed:
- Add HF-specific validation rules
- Add LVEF range validation
- Add HF-specific constants

### AFIB Migration Requirements

#### Database Schema Updates Needed:
```sql
-- Add missing AFIB-specific fields to reviews table
ALTER TABLE reviews ADD COLUMN afib_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN aflutter_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN af_foundonly_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN no_af_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN afib_encounter_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN afib_history_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN aflutter_encounter_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN aflutter_history_flag TINYINT(1);
ALTER TABLE reviews ADD COLUMN af_timing ENUM('Presented in AF','AF started after admission');
ALTER TABLE reviews ADD COLUMN af_type ENUM('paroxysmal','persistent','permanent','unknown');
-- ... (many more AFIB-specific fields)
```

#### Frontend Updates Needed:
- Complete rewrite of AFIB EventReview component
- Add complex conditional form logic
- Add all AFIB-specific form fields
- Implement proper validation

#### Backend Updates Needed:
- Add AFIB-specific validation rules
- Add AFIB-specific constants
- Add AFIB-specific business logic

## Updated Architecture Recommendations

### 1. Enhanced Study-Specific Components

**Current Status:** Basic study-specific components created
**Required:** Full-featured study-specific components matching CakePHP functionality

### 2. Prescrub Workflow Implementation

**Current Status:** Schema includes prescrub, but no implementation
**Required:** Full prescrub workflow implementation for VTE

### 3. Complex Form Logic

**Current Status:** Simple forms created
**Required:** Complex conditional form logic matching CakePHP forms

### 4. Validation Rules

**Current Status:** Basic validation
**Required:** Study-specific validation rules matching CakePHP logic

## Migration Priority Recommendations

### Phase 1: Core Infrastructure (Already Done)
- ✅ Single repository architecture
- ✅ Study configuration system
- ✅ Basic database schemas
- ✅ Basic study-specific models

### Phase 2: VTE Migration (High Priority)
- 🔄 Complete VTE review form implementation
- 🔄 Implement prescrub workflow
- 🔄 Add VTE-specific validation
- 🔄 Test VTE workflow end-to-end

### Phase 3: Heart Failure Migration (Medium Priority)
- 🔄 Complete HF review form implementation
- 🔄 Add HF-specific validation
- 🔄 Test HF workflow end-to-end

### Phase 4: AFIB Migration (Low Priority)
- 🔄 Complete AFIB review form implementation (1000+ lines)
- 🔄 Add AFIB-specific validation
- 🔄 Test AFIB workflow end-to-end
- 🔄 Implement when needed (currently no plans)

## Conclusion

Our multi-study architecture plan is fundamentally sound and correctly identifies the key differences between studies. The main gaps are in the complexity and completeness of the study-specific components, particularly:

1. **VTE**: Needs full prescrub workflow and complex review form (1000+ lines)
2. **Heart Failure**: Needs enhanced review form with HF-specific fields
3. **AFIB**: Needs complex review form with AFIB-specific fields (1000+ lines)
4. **All Studies**: Need complete validation rules and business logic

The single repository approach with separate deployments remains the optimal solution, but we need to invest more effort in creating complete, production-ready study-specific components that match the functionality of the existing CakePHP applications.
