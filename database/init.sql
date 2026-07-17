USE agrochain_saas;

CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subdomain VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanant_id INT, 
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('farmer', 'buyer', 'driver') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);


-- Demo Seed
INSERT INTO tenants (name, subdomain) VALUES
('Volta Farmers Co-op', 'volta'),
('Ashanti Agri-Union', 'ashanti')

INSERT INTO users (tenants_id, name, email, role, password_hash) VALUES
(1, "Kwame Annor-Baah Tawiah", 'kwame@volta.com', 'farmer', '$2y$10$WqfVjI6h9gB2Z.yIeZ7Gve.L10L9Q9D91gH0sT6RxeLq/v098n/F6')