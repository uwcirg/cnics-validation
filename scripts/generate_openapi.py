import os
import sys
from apispec import APISpec
from flask import Flask
import inspect
import yaml

# Allow imports of backend package
sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))
from flask_backend.app import app

spec = APISpec(
    title="CNICS Validation API",
    version="1.0.0",
    openapi_version="3.0.3",
)

def extract_operations(func):
    doc = inspect.getdoc(func) or ""
    if '---' in doc:
        yaml_part = doc.split('---', 1)[1]
        return yaml.safe_load(yaml_part)
    return {}

with app.app_context():
    for rule in app.url_map.iter_rules():
        if rule.rule.startswith("/api/"):
            view = app.view_functions[rule.endpoint]
            ops = extract_operations(view)
            if ops:
                ops = {"get": ops}
            path = rule.rule.replace('<', '{').replace('>', '}')
            spec.path(path=path, operations=ops)

with open("openapi.json", "w") as f:
    f.write(spec.to_yaml())
    f.write("\n")
