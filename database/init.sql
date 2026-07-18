-- =============================================================================
-- AGROCHAIN — DATABASE SCHEMA
-- =============================================================================
-- Runs once, automatically, the first time the MySQL container starts on a
-- fresh volume. If you've already got a db_data volume from a previous run,
-- editing this file alone does nothing — MySQL only executes files in
-- /docker-entrypoint-initdb.d on an EMPTY data directory. To pick up changes:
--     docker compose down -v
--     docker compose up -d --build
-- =============================================================================

USE agrochain_saas;

-- -----------------------------------------------------------------------------
-- tenants: one row per organization using the platform (a farmer co-op, an
-- agri-union, etc.). This is the top of the multi-tenancy hierarchy —
-- everything else in the schema ultimately traces back to a tenant.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subdomain VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- users: every person with a login — farmers, buyers, drivers.
--
-- tenant_id is now NOT NULL. Previously it allowed NULL, which meant anyone
-- who registered through register.php never actually belonged to a tenant —
-- defeating the point of a "multi-tenant" schema. Every user now has to be
-- assigned to a real tenant at signup (register.php was updated to require
-- picking one from a dropdown).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('farmer', 'buyer', 'driver') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------------------------
-- products: crop listings a farmer publishes. farmer_id points at a user
-- whose role happens to be 'farmer' — MySQL's ENUM doesn't let us enforce
-- "must be a farmer" at the schema level, so that check has to live in the
-- PHP that inserts here (validate $user['role'] === 'farmer' before allowing
-- a listing to be created).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    crop_type VARCHAR(100) NOT NULL,
    quantity_bags INT NOT NULL,
    price_per_bag DECIMAL(10, 2) NOT NULL,
    region VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------------------------
-- orders: a buyer acting on a listing (build-order step 3, not wired up in
-- the app yet — this just gets the table in place ahead of time so we're
-- not doing another schema migration mid-build).
--
-- quantity_bags is duplicated here rather than just referencing the
-- product's quantity, because a buyer might only want PART of a listing
-- (e.g. farmer lists 50 bags, buyer orders 20) — the order needs its own
-- quantity, separate from whatever's left in the listing.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    quantity_bags INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------------------------
-- documents: verification files a user uploads. Started out farmer-only
-- (crop quality certificates) but drivers need their own verification too
-- (license, vehicle insurance) before a co-op would trust them to move
-- produce — so this is user_id + category rather than farmer_id, to cover
-- both without a second near-identical table.
--
-- The actual file bytes will live in S3 once the AWS work happens — this
-- table only ever stores WHERE the file is, never the file itself.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category ENUM('crop_quality_certificate', 'drivers_license', 'vehicle_insurance') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------------------------------------
-- Demo seed
-- -----------------------------------------------------------------------------
INSERT INTO tenants (name, subdomain) VALUES
    ('Volta Farmers Co-op', 'volta'),
    ('Ashanti Agri-Union', 'ashanti');

-- Login with kwame@volta.com / password123
INSERT INTO users (tenant_id, name, email, role, password_hash) VALUES
    (1, 'Kwame Annor-Baah Tawiah', 'kwame@volta.com', 'farmer',
     '$2y$10$WYiC0OKQgl/VhY0U/EdAy.oqW3vGh1Yk4fkjJQM.sqVAQAx.m5wVq');