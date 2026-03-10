-- Tabla de proveedores
CREATE TABLE proveedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  contacto VARCHAR(100),
  telefono VARCHAR(30),
  email VARCHAR(100),
  direccion VARCHAR(255),
  cuit VARCHAR(20),
  condicion_iva VARCHAR(50),
  observaciones TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de compras
CREATE TABLE compras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proveedor_id INT NOT NULL,
  fecha DATE NOT NULL,
  numero_factura VARCHAR(50),
  monto DECIMAL(12,2) NOT NULL,
  detalle TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('PENDIENTE','CONSOLIDADA') DEFAULT 'PENDIENTE',
  pago_id INT DEFAULT NULL,
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);

-- Tabla de pagos a proveedores
CREATE TABLE pagos_proveedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proveedor_id INT NOT NULL,
  fecha DATE NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  comprobante VARCHAR(100),
  observaciones TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);
