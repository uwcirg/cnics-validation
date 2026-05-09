# Study Configuration Factory
# This module provides study-specific configuration loading
# Uncomment and modify as needed for each study deployment

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
