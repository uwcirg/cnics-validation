import importlib
from docx import Document


app_mod = importlib.import_module('flask_backend.app')
app = app_mod.app


def test_serve_doc(tmp_path, monkeypatch):
    monkeypatch.setattr(app_mod, 'FILES_DIR', str(tmp_path))
    doc = tmp_path / 'sample.doc'
    doc.write_text('hello')
    client = app.test_client()
    resp = client.get('/files/sample.doc')
    assert resp.status_code == 200
    assert resp.data == b'hello'


def test_generate_pdf(tmp_path, monkeypatch):
    monkeypatch.setattr(app_mod, 'FILES_DIR', str(tmp_path))
    docx_path = tmp_path / 'sample.docx'
    d = Document()
    d.add_paragraph('hi')
    d.save(docx_path)
    client = app.test_client()
    resp = client.get('/files/sample.pdf')
    assert resp.status_code == 200
    assert (tmp_path / 'sample.pdf').exists()
