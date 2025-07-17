import os
from docx import Document
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter

FILES_DIR = os.getenv('FILES_DIR', 'app/webroot/files')

for fname in os.listdir(FILES_DIR):
    if fname.lower().endswith('.doc'):
        base = fname[:-4]
        pdf_path = os.path.join(FILES_DIR, base + '.pdf')
        if os.path.exists(pdf_path):
            continue
        doc_path = os.path.join(FILES_DIR, fname)
        # try to read as docx
        try:
            doc = Document(doc_path)
            text = '\n'.join(p.text for p in doc.paragraphs)
            if not text.strip():
                raise ValueError('empty')
        except Exception:
            # couldn't parse doc, just create stub
            text = (
                f'PDF version of {fname} is not available. ' \
                f'Please open the .doc file instead.'
            )
        c = canvas.Canvas(pdf_path, pagesize=letter)
        width, height = letter
        y = height - 40
        for line in text.split('\n'):
            c.drawString(40, y, line)
            y -= 15
            if y < 40:
                c.showPage()
                y = height - 40
        c.save()
        print('created', pdf_path)
