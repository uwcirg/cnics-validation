FROM python:3.12-slim

WORKDIR /app

# Install dependencies first to leverage Docker layer caching
COPY requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt && rm /tmp/requirements.txt

# Copy the backend source into a package directory so `python -m flask_backend.app`
# can locate the module correctly.
COPY . /app/flask_backend

CMD ["python", "-m", "flask_backend.app"]
