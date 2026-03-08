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

CREATE TABLE plots (
    plot_id INT AUTO_INCREMENT PRIMARY KEY,
    plot_number INT NOT NULL UNIQUE,
    plot_name VARCHAR(50) NOT NULL
);

-- 15 Plot --
INSERT INTO plots (plot_number, plot_name) VALUES
(1,'Plot 1'),(2,'Plot 2'),(3,'Plot 3'),(4,'Plot 4'),(5,'Plot 5'),
(6,'Plot 6'),(7,'Plot 7'),(8,'Plot 8'),(9,'Plot 9'),(10,'Plot 10'),
(11,'Plot 11'),(12,'Plot 12'),(13,'Plot 13'),(14,'Plot 14'),(15,'Plot 15');

CREATE TABLE plot_seedlings (
    plot_seedling_id INT AUTO_INCREMENT PRIMARY KEY,
    plot_id INT NOT NULL,
    variety_id INT NOT NULL,
    staff_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plot_id) REFERENCES plots(plot_id),
    FOREIGN KEY (variety_id) REFERENCES varieties(variety_id),
    FOREIGN KEY (staff_id) REFERENCES staff_info(staff_id)
);

CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    variety_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (variety_id) REFERENCES varieties(variety_id)
);

CREATE TABLE inventory_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    variety_id INT NOT NULL,
    quantity INT NOT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_info(staff_id),
    FOREIGN KEY (variety_id) REFERENCES varieties(variety_id)
);

CREATE TABLE damage_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    plot_id INT NOT NULL,
    plot_seedling_id INT NOT NULL,
    staff_id INT NOT NULL,
    quantity_damaged INT NOT NULL,
    description TEXT,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plot_id) REFERENCES plots(plot_id),
    FOREIGN KEY (plot_seedling_id) REFERENCES plot_seedlings(plot_seedling_id),
    FOREIGN KEY (staff_id) REFERENCES staff_info(staff_id)
);

CREATE TABLE damage_photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (report_id) REFERENCES damage_reports(report_id)
);

CREATE TABLE customer_info (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT NOT NULL,
    ordered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer_info(customer_id),
    FOREIGN KEY (staff_id) REFERENCES staff_info(staff_id)
);

CREATE TABLE order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    variety_id INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (variety_id) REFERENCES varieties(variety_id)
);