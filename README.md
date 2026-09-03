# GCCP (PHP) - README

This repository contains a PHP authentication demo application. The project is prepared to be deployed on Render using the provided Dockerfile.

Quick start (local using Docker Compose)

1. Copy .env.example to .env and edit values:
   cp .env.example .env

2. Start services:
   docker-compose up --build

3. Visit: http://localhost:8080/auth/install.php to run the installer (create DB and config if needed).

Deploying to Render

1. Create a new Web Service on Render and connect your GitHub repository.
2. Select "Docker" as the environment; Render will use the repository Dockerfile.
3. Set the following environment variables in Render's dashboard (Settings > Environment):
   - DB_HOST, DB_NAME, DB_USER, DB_PASS (or DATABASE_URL)
   - PASSWORD_PEPPER — a long random secret kept out of the database
   - APP_ENV=production
4. (Optional) Provision a managed PostgreSQL/MySQL database on Render and attach it to your service.
5. Deploy; once the service is healthy, visit your Render URL and run /auth/install.php to initialize the database if necessary.

Security notes
- PASSWORD_PEPPER must never be stored in the database or committed to Git. Store it in Render environment variables or a secret manager.
- Rotate the pepper if you suspect compromise. Rotation requires users to re-authenticate so their passwords can be rehashed.
- Disable the installer after initial setup (remove or restrict install.php) to avoid accidental reconfiguration.

