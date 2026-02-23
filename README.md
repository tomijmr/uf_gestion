# Universal Fitness ERP - Sistema de Gestión Integral

## 📋 Descripción General

**Universal Fitness ERP** es una solución empresarial completa diseñada para la gestión integral de operaciones de manufactura y venta. El sistema abarca desde el primer contacto con el cliente hasta la entrega del producto final, pasando por una gestión detallada de la producción, inventarios, recursos humanos y finanzas.

Este sistema está optimizado para empresas de manufactura que requieren trazabilidad en su línea de producción, control estricto de stock y una gestión eficiente de su personal.

---

## 🛠️ Stack Tecnológico

- **Backend:** PHP 7.4+ (Sin frameworks pesados, arquitectura MVC ligera)
- **Base de Datos:** MySQL / MariaDB
- **Frontend:** Bootstrap 5, JavaScript (Vanilla + jQuery para componentes específicos)
- **Reportes:** FPDF para generación de PDFs, Librerías de gráficos para Dashboard
- **Infraestructura:** Compatible con XAMPP/LAMP/WAMP

---

## 🚀 Módulos del Sistema

### 1. 🏭 Producción Avanzada
El corazón del sistema, diseñado para un flujo de trabajo industrial.
- **Workflow de 9 Estados:** Desde "Corte" hasta "Empaque" y "Despacho".
- **Trazabilidad QR:** Generación de tickets de producción con códigos QR para seguimiento en planta.
- **Control de Calidad (QC):** Puntos de aprobación obligatorios entre etapas críticas.
- **Descuento de Stock por Etapa:** El sistema descuenta materias primas automáticamente según la etapa del proceso.
- **Panel de Operarios:** Interfaz simplificada para que los operarios actualicen estados mediante escaneo o selección.

### 2. 👥 Recursos Humanos y Nómina
Sistema completo para la gestión del personal y sus pagos.
- **Legajos Digitales:** Información completa, historial y documentación.
- **Gestión de Asistencia:** Registro de entradas, salidas y horas extras.
- **Nómina Semanal:**
  - Cálculo automático basado en horas trabajadas o sueldo fijo.
  - Gestión de **Períodos de Pago** (apertura y cierre).
  - Manejo de **Adelantos, Préstamos y Descuentos**.
  - Generación de recibos y reportes de pago.

### 3. 📦 Inventario y Logística
Control total sobre los materiales y productos.
- **Materias Primas:** Stock en tiempo real, alertas de bajo stock, valoración de inventario.
- **Productos Terminados:** Stock de productos listos para venta.
- **Movimientos de Stock:** Registro de ingresos, egresos, ajustes y devoluciones.
- **Reportes de Stock:** Análisis de movimientos y previsión de necesidades.

### 4. 💼 Gestión Comercial (CRM)
- **Clientes:** Base de datos con historial de compras, estados de cuenta y saldos (Deuda).
- **Pedidos:** Creación, seguimiento y facturación de órdenes de venta.
- **Presupuestos:** Generación y envío de cotizaciones que se convierten en pedidos.

### 5. 💰 Finanzas y Compras
- **Compras a Proveedores:** Gestión de órdenes de compra y recepción de mercadería.
- **Caja Chica:** Control de gastos diarios y movimientos de efectivo.
- **Auditoría:** Módulo avanzado (`auditoria.php`) para conciliación de caja, reportes de ventas vs. costos y detección de discrepancias.
- **Dashboard de KPIs:** Indicadores clave de rendimiento en tiempo real (Ventas del día, Producción en curso, Cobranzas pendientes).

---

## 📂 Estructura del Proyecto

```
/
├── app/                  # Lógica de negocio y configuraciones
│   ├── db.php            # Conexión a Base de Datos
│   ├── auth.php          # Sistema de autenticación
│   ├── kpis.php          # Indicadores de rendimiento
│   └── ...
├── public/               # Archivos accesibles vía web (Vistas + Controladores)
│   ├── index.php         # Dashboard principal
│   ├── pedidos.php       # Gestión de ventas
│   ├── op.php            # Órdenes de Producción (Sistema Avanzado)
│   ├── empleados.php     # Módulo de RRHH y Nómina
│   ├── auditoria.php     # Reportes financieros  
│   └── ...
├── storage/              # Archivos generados, logs, uploads
├── scripts/              # Scripts de mantenimiento y utilidades
└── views/                # Fragmentos de vista reutilizables (partials)
```

---

## 🔧 Instalación y Despliegue

### Requisitos Previos
- Servidor Web (Apache/Nginx)
- PHP 7.4 o superior
- MySQL 5.7 o MariaDB 10.3+

### Pasos de Instalación
1. **Clonar el repositorio** en la carpeta pública del servidor web.
2. **Base de Datos:**
   - Crear una base de datos vacía (ej. `erp_mvp`).
   - Importar `a0011086_erp_mvp_fulldb.sql` para la estructura base.
   - Ejecutar los scripts de migración en orden si es una actualización:
     - `sistema_produccion_avanzado.sql`
     - `migracion_nomina_asistencias.sql`
     - `agregar_columnas_iva.sql`
3. **Configuración:**
   - Editar `app/db.php` o configurar variables de entorno (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
4. **Permisos:** Asegurar permisos de escritura en la carpeta `storage/`.

---

## 📄 Notas de Versión

- **Versión Prod 2.0:** Integración completa de módulo de producción con QR y sistema de nómina semanal.
- **Mejoras Recientes:**
  - Sistema de deuda de clientes.
  - Pagos manuales en nómina con cálculo dinámico.
  - Auditoría financiera avanzada.

---
© 2026 Universal Fitness SA. Todos los derechos reservados.
