# Separation of Duties

The project is split into a React frontend and a Flask backend. The backend exposes REST endpoints which the frontend fetches to display data.

- **Backend (`flask_backend/`)** – Provides an API for retrieving table data from the database. The backend is responsible for applying authentication and preparing JSON responses consumed by the UI.
- **Frontend (`frontend/`)** – React application that renders the user interface. It communicates with the backend via `VITE_API_URL` and displays the data returned by the API.

The backend does not render HTML templates. Instead, it returns JSON that the frontend uses to build views on each page.

