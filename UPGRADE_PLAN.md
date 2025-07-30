# CNICS Validation / Adjudication Modernization Plan

This repository contains a legacy CakePHP application. The goal is to modernize
it using a "system of systems" approach as outlined in *CNICS Validation /
Adjudication systems - path forward summer 2025*. The new architecture will rely
on containerized services configured through environment files similar to the
[ltt-environments](https://github.com/uwcirg/ltt-environments) examples.

## Target Architecture

1. **Container Orchestration**
   - Use `docker-compose` based on the pattern in
     [ltt-environments/base/docker-compose.yaml](https://github.com/uwcirg/ltt-environments/blob/main/base/docker-compose.yaml).
   - Define services in an environment file for repeatable deployments.

2. **Core Services**
   - `logserver` for centralized logging. Example configuration available in
     [logs/docker-compose.yaml](https://github.com/uwcirg/ltt-environments/blob/main/logs/docker-compose.yaml).
   - `keycloak` for authentication and authorization.
   - `mariadb`/`mysql` as the application database.
   - Web application container running the new validation/adjudication UI.
   - Optional SMART on FHIR PRO module if questionnaires/surveys are required.

3. **Web Application**
   - Replace the CakePHP interface with a modern JavaScript framework such as
     React or Vue. The framework can reside in its own container and communicate
     with the existing PHP code via APIs or gradually replace the back-end.

4. **Development Workflow**
   - Provide a sample `docker-compose.yml` and `.env` file in this repository for
     local development.
   - Use container images to mirror production as closely as possible.
   - Adopt automated testing and continuous integration.

5. **Data Migration**
   - Review existing database schema. If schema changes are required, create SQL
     migrations and document the steps.
   - Ensure data from the current MySQL database is migrated into the new
     containerized database without loss.

6. **LLM Assistance**
   - Where appropriate, use language model tooling to help refactor legacy PHP
     code and to scaffold new components.

## Getting Started

1. Install Docker and Docker Compose.
2. Copy the example environment configuration:

   ```bash
   cp env.example .env
   ```
3. Run the stack:

   ```bash
   docker-compose up -d
   ```
4. Access the application at the configured URL.

## Next Steps

This plan provides a high-level path to bring the project up to modern
standards. Each subsystem can be introduced incrementally, allowing gradual
migration from the legacy CakePHP codebase to a more maintainable architecture.


