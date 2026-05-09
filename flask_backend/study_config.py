# Study Configuration Factory — SCAFFOLDING ONLY
#
# Not currently imported by any runtime code. Preserved as the dispatch
# scaffolding that the multi-study pattern mandated by Constitution
# Principle IV ("Configuration Over Code Forks") will eventually need
# to load per-study models, schemas, and feature flags based on the
# `STUDY_TYPE` environment variable.
#
# As of the first release, only MCI is implemented; calling this module
# is a no-op for the running stack. See `docs/template-setup-guide.md`
# (Step 3 of the Step-by-Step Multi-Study Deployment Process) for how
# this would be wired up when a second study (e.g., VTE) is brought
# online.
#
# Per Constitution Principle VI ("unused subsystem hygiene"), if this
# module is still unused at the time of the first tagged release and
# no near-term plan exists to wire it up, it should be either deleted
# or this banner re-evaluated.

import os

def get_study_type():
    """Get the current study type from environment variables"""
    return os.getenv('STUDY_TYPE', 'mci')

def get_study_config():
    """Get study-specific configuration based on STUDY_TYPE"""
    study_type = get_study_type()
    
    configs = {
        'mci': {
            'name': 'Myocardial Infarction',
            'abbreviation': 'MI',
            'models_module': 'flask_backend.models',  # Current MI models
            'schema_file': 'init/02-schema.sql',
            'features': {
                'pre_scrub': False,
                'questionnaires': False,
                'cardiac_interventions': True
            }
        },
        # 'vte': {
        #     'name': 'VTE Validation',
        #     'abbreviation': 'VTE',
        #     'models_module': 'flask_backend.models.studies.vte',
        #     'schema_file': 'init/02-schema-vte.sql',
        #     'features': {
        #         'pre_scrub': True,
        #         'questionnaires': False,
        #         'prescrub_rejection': True
        #     }
        # },
        # 'cva': {
        #     'name': 'CVA Validation',
        #     'abbreviation': 'CVA',
        #     'models_module': 'flask_backend.models.studies.cva',
        #     'schema_file': 'init/02-schema-cva.sql',
        #     'features': {
        #         'pre_scrub': False,
        #         'questionnaires': True,
        #         'survey_module': True
        #     }
        # },
        # 'hf': {
        #     'name': 'Heart Failure Validation',
        #     'abbreviation': 'HF',
        #     'models_module': 'flask_backend.models.studies.hf',
        #     'schema_file': 'init/02-schema-hf.sql',
        #     'features': {
        #         'pre_scrub': False,
        #         'questionnaires': False,
        #         'ef_measurement': True
        #     }
        # },
        # 'afib': {
        #     'name': 'AFIB Validation',
        #     'abbreviation': 'AFIB',
        #     'models_module': 'flask_backend.models.studies.afib',
        #     'schema_file': 'init/02-schema-afib.sql',
        #     'features': {
        #         'pre_scrub': False,
        #         'questionnaires': False,
        #         'status': 'inactive'
        #     }
        # }
    }
    
    return configs.get(study_type, configs['mci'])

def load_study_models():
    """Load study-specific models based on current study type"""
    config = get_study_config()
    models_module = config['models_module']
    
    try:
        # Import the appropriate models module
        if models_module == 'flask_backend.models':
            # Use current MI models
            from flask_backend import models
            return models
        else:
            # Import study-specific models
            import importlib
            return importlib.import_module(models_module)
    except ImportError as e:
        print(f"Warning: Could not load study models from {models_module}: {e}")
        # Fall back to default MI models
        from flask_backend import models
        return models

def get_study_features():
    """Get study-specific feature flags"""
    config = get_study_config()
    return config.get('features', {})

def is_feature_enabled(feature_name):
    """Check if a specific feature is enabled for the current study"""
    features = get_study_features()
    return features.get(feature_name, False)
