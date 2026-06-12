-- ============================================================
-- ESQUEMA FINAL — App de pedidos QR para bares (MVP)
-- ============================================================
-- Este script reconstruye la base de datos desde cero con la
-- versión definitiva del diseño, lista para construir el backend.
--
-- Cambios respecto a la versión inicial:
--   1. Nueva tabla sesion_mesa (separa grupos que rotan en la misma mesa)
--   2. pedido ahora cuelga de sesion_mesa, no de mesa directamente
--   3. Restricciones CHECK en campos de valores cerrados
--   4. Campos actualizado_en donde aplican
--   5. Índices en todas las claves foráneas
--   6. Comportamiento ON DELETE definido en cada FK
--
-- Ejecutar TODO el script de una vez (Alt+X en DBeaver).
-- ============================================================

-- ============================================================
-- 0. LIMPIEZA — borrar tablas existentes si las hay
-- ============================================================
-- El orden importa: primero las que dependen de otras.
DROP TABLE IF EXISTS linea_pedido  CASCADE;
DROP TABLE IF EXISTS pedido        CASCADE;
DROP TABLE IF EXISTS sesion_mesa   CASCADE;
DROP TABLE IF EXISTS producto      CASCADE;
DROP TABLE IF EXISTS categoria     CASCADE;
DROP TABLE IF EXISTS mesa          CASCADE;
DROP TABLE IF EXISTS usuario       CASCADE;
DROP TABLE IF EXISTS restaurante   CASCADE;


-- ============================================================
-- 1. RESTAURANTE — la raíz, el bar
-- ============================================================
CREATE TABLE restaurante (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre            TEXT        NOT NULL,
    slug              TEXT        NOT NULL UNIQUE,
    logo_url          TEXT,
    idiomas_activos   TEXT        NOT NULL DEFAULT 'es',
    activo            BOOLEAN     NOT NULL DEFAULT true,
    creado_en         TIMESTAMPTZ NOT NULL DEFAULT now(),
    actualizado_en    TIMESTAMPTZ NOT NULL DEFAULT now()
);


-- ============================================================
-- 2. USUARIO — dueños y meseros del bar
-- ============================================================
CREATE TABLE usuario (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    restaurante_id  BIGINT      NOT NULL REFERENCES restaurante(id) ON DELETE CASCADE,
    email           TEXT        NOT NULL UNIQUE,
    password_hash   TEXT        NOT NULL,
    rol             TEXT        NOT NULL DEFAULT 'personal'
                    CHECK (rol IN ('dueño', 'personal')),
    activo          BOOLEAN     NOT NULL DEFAULT true,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_usuario_restaurante ON usuario(restaurante_id);


-- ============================================================
-- 3. MESA — mesas físicas del bar con su token QR
-- ============================================================
CREATE TABLE mesa (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    restaurante_id  BIGINT      NOT NULL REFERENCES restaurante(id) ON DELETE CASCADE,
    numero          TEXT        NOT NULL,
    token_qr        TEXT        NOT NULL UNIQUE,
    activa          BOOLEAN     NOT NULL DEFAULT true,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_mesa_restaurante ON mesa(restaurante_id);


-- ============================================================
-- 4. CATEGORIA — secciones de la carta (Bebidas, Tapas...)
-- ============================================================
CREATE TABLE categoria (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    restaurante_id  BIGINT      NOT NULL REFERENCES restaurante(id) ON DELETE CASCADE,
    nombre_es       TEXT        NOT NULL,
    nombre_en       TEXT,
    nombre_fr       TEXT,
    orden           INTEGER     NOT NULL DEFAULT 0,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_categoria_restaurante ON categoria(restaurante_id);


-- ============================================================
-- 5. PRODUCTO — platos y bebidas de la carta
-- ============================================================
CREATE TABLE producto (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    categoria_id    BIGINT      NOT NULL REFERENCES categoria(id) ON DELETE RESTRICT,
    nombre_es       TEXT        NOT NULL,
    nombre_en       TEXT,
    nombre_fr       TEXT,
    descripcion_es  TEXT,
    descripcion_en  TEXT,
    descripcion_fr  TEXT,
    precio_centimos INTEGER     NOT NULL CHECK (precio_centimos >= 0),
    foto_url        TEXT,
    disponible      BOOLEAN     NOT NULL DEFAULT true,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now(),
    actualizado_en  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_producto_categoria ON producto(categoria_id);


-- ============================================================
-- 6. SESION_MESA — agrupa los pedidos de un mismo grupo de clientes
-- ============================================================
-- Una mesa puede tener varias sesiones a lo largo del día (rotación
-- de grupos). Solo puede haber UNA sesión abierta por mesa a la vez.
-- Se abre automática al primer pedido si no hay activa, o manual
-- por el mesero. Se cierra solo manual cuando el mesero cobra.
-- ============================================================
CREATE TABLE sesion_mesa (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    mesa_id         BIGINT      NOT NULL REFERENCES mesa(id) ON DELETE RESTRICT,
    estado          TEXT        NOT NULL DEFAULT 'abierta'
                    CHECK (estado IN ('abierta', 'cerrada')),
    abierta_por     TEXT        NOT NULL
                    CHECK (abierta_por IN ('automatica', 'mesero')),
    abierta_en      TIMESTAMPTZ NOT NULL DEFAULT now(),
    cerrada_en      TIMESTAMPTZ,
    cerrada_por_mesero_id BIGINT REFERENCES usuario(id) ON DELETE SET NULL
);

CREATE INDEX idx_sesion_mesa ON sesion_mesa(mesa_id);

-- NOTA: NO se restringe a una sola sesión abierta por mesa.
-- A propósito: en la misma mesa pueden coexistir varias sesiones
-- abiertas si un comensal o subgrupo quiere pagar por separado.
-- Cada sesión es una "unidad de pago" independiente.


-- ============================================================
-- 7. PEDIDO — un pedido dentro de una sesión
-- ============================================================
-- Pedido cuelga de sesion_mesa (no de mesa directamente).
-- Mismo objeto venga del cliente (por QR) o del mesero (comandero).
-- ============================================================
CREATE TABLE pedido (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    sesion_mesa_id  BIGINT      NOT NULL REFERENCES sesion_mesa(id) ON DELETE RESTRICT,
    estado          TEXT        NOT NULL DEFAULT 'recibido'
                    CHECK (estado IN ('recibido', 'en_preparacion', 'servido', 'cancelado')),
    origen          TEXT        NOT NULL
                    CHECK (origen IN ('cliente', 'mesero')),
    mesero_id       BIGINT      REFERENCES usuario(id) ON DELETE SET NULL,
    nota_general    TEXT,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now(),
    actualizado_en  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_pedido_sesion  ON pedido(sesion_mesa_id);
CREATE INDEX idx_pedido_mesero  ON pedido(mesero_id);
CREATE INDEX idx_pedido_estado  ON pedido(estado);


-- ============================================================
-- 8. LINEA_PEDIDO — cada renglón del pedido
-- ============================================================
-- El precio se COPIA aquí en el momento del pedido y queda congelado.
-- ============================================================
CREATE TABLE linea_pedido (
    id                        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pedido_id                 BIGINT  NOT NULL REFERENCES pedido(id)   ON DELETE CASCADE,
    producto_id               BIGINT  NOT NULL REFERENCES producto(id) ON DELETE RESTRICT,
    cantidad                  INTEGER NOT NULL DEFAULT 1 CHECK (cantidad > 0),
    precio_unitario_centimos  INTEGER NOT NULL CHECK (precio_unitario_centimos >= 0),
    nota                      TEXT,
    estado                    TEXT    NOT NULL DEFAULT 'activa'
                              CHECK (estado IN ('activa', 'no_disponible', 'cancelada')),
    creado_en                 TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_linea_pedido   ON linea_pedido(pedido_id);
CREATE INDEX idx_linea_producto ON linea_pedido(producto_id);


-- ============================================================
-- FIN DEL ESQUEMA
-- ============================================================
-- Para verificar que se crearon las 8 tablas:
--   SELECT table_name FROM information_schema.tables
--   WHERE table_schema = 'public' ORDER BY table_name;
-- ============================================================
