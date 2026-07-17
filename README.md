# AgroChain SaaS

A secure multi-tenant SaaS platform for agricultural supply chain tracking.

## Getting Started 🐳

To run this application locally with Docker, follow these steps:

1. **Clone the repository:**
   ```bash
   git clone <your-github-repo-link>
   cd SENSI_SAAS

```

2. **Set up your environment file:**
Create a `.env` file in the root directory and add your database credentials:
```.env
MYSQL_ROOT_PASSWORD=kay_bart_<3
MYSQL_DATABASE=agrochain_saas
MYSQL_USER=<your username>
MYSQL_PASSWORD=<your_password>

```


3. **Start the containers:**
```bash
docker compose up -d

```



The application will automatically initialize the database using the script in `database/init.sql` and will be available at `http://localhost:8080`.
