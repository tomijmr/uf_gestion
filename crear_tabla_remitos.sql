-- Tabla de remitos
CREATE TABLE remitos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(32) NOT NULL,
  fecha DATE NOT NULL,
  order_id INT NOT NULL,
  cliente_nombre VARCHAR(255) NOT NULL,
  cuit_dni VARCHAR(32),
  telefono VARCHAR(32),
  direccion VARCHAR(255),
  fecha_pedido DATE,
  fecha_pactada DATE,
  transporte VARCHAR(255),
  bultos INT,
  tipo_envio VARCHAR(32),
  nombre_sucursal VARCHAR(255),
  direccion_sucursal VARCHAR(255),
  observaciones TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Índice para búsqueda rápida por número y fecha
CREATE INDEX idx_remitos_numero ON remitos(numero);
CREATE INDEX idx_remitos_fecha ON remitos(fecha);
