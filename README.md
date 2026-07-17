# AgroChain SaaS

A secure multi-tenant SaaS platform for agricultural supply chain tracking.

## Getting Started 🐳

To run this application locally with Docker, follow these steps:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Cyberpunk-Edge-Runners/agrochain-saas.git
   cd agrochain-saas
   ```

2. **Set up your environment file:**
   Create a `.env` file in the root directory and add your database credentials.
   These must use the `DB_` prefix shown below — `docker-compose.yml` reads
   `${DB_ROOT_PASSWORD}`, `${DB_DATABASE}`, `${DB_USER}`, and `${DB_PASSWORD}`
   specifically, so the variable names below have to match exactly or the
   database container will start with an empty/misconfigured database.
   ```.env
   DB_ROOT_PASSWORD=<your_root_password>
   DB_DATABASE=agrochain_saas
   DB_USER=<your_username>
   DB_PASSWORD=<your_password>
   ```

3. **Start the containers:**
   ```bash
   docker compose up -d
   ```

The application will automatically initialize the database using the script in `database/init.sql` and will be available at `http://localhost:8080`.