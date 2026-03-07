CREATE TABLE admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE staff_info (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    contact VARCHAR(20) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE seedlings (
    seedling_id INT AUTO_INCREMENT PRIMARY KEY,
    seedling_name VARCHAR(100) NOT NULL
);
CREATE TABLE varieties (
    variety_id INT AUTO_INCREMENT PRIMARY KEY,
    seedling_id INT NOT NULL,
    variety_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (seedling_id) REFERENCES seedlings(seedling_id)
);