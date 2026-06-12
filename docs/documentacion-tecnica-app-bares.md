# App de pedidos QR para bares — Documentación técnica

**Estado:** diseño de datos completo, esquema implementado en PostgreSQL, consultas principales validadas. Pendiente: backend (API) y frontend.

**Stack:**
- Base de datos: PostgreSQL
- Cliente de BD para desarrollo: DBeaver
- Lenguaje del backend: por decidir (PHP o Python)
- Frontend: web (sin app instalable)

---

## 1. QUÉ HACE EL PRODUCTO

App SaaS multi-tenant para bares y cafeterías. Permite que:

- **Clientes** escaneen un QR en la mesa y pidan desde su móvil sin instalar nada.
- **Meseros** tomen comandas desde su móvil (comandero) en lugar de papel.
- **Cocina** vea los pedidos en tiempo real en un tablero (polling).
- **Dueños** gestionen la carta, mesas, idiomas y meseros desde un panel.

**No incluye en el MVP:** pasarela de pago ni facturación fiscal. El cobro lo hace el TPV del bar para evitar la normativa española Verifactu en el MVP.

**Idiomas soportados:** ES / EN / FR.
**Mercado inicial:** bares y cafeterías de O Barco de Valdeorras y comarca.

---

## 2. CÓMO SE RELACIONAN LAS TABLAS

Esta es la sección más importante. Antes de mirar SQL, hay que entender la lógica de pertenencia entre las tablas.

### 2.1. Las dos ideas base

**Idea 1 — Cada tabla tiene un `id` único (clave primaria, PK).**
Cada fila de cada tabla tiene un `id` autogenerado que la identifica. La Caña tiene `id=1` en `producto`. El Bar O Lar tiene `id=1` en `restaurante`. Ese `id` no se repite dentro de su tabla.

**Idea 2 — Las tablas se conectan guardando el `id` de otra tabla (clave foránea, FK).**
Cuando una tabla quiere "apuntar" a otra, lo hace guardando el `id` de la otra tabla en una columna llamada habitualmente `tablaapuntada_id`. Por ejemplo, la tabla `mesa` tiene una columna `restaurante_id` que guarda el `id` del restaurante dueño de esa mesa.

PostgreSQL garantiza la **integridad referencial** de las FKs: si intentas meter un valor en una FK que no existe en la tabla referenciada, la base lo rechaza con error.

Toda la relación entre tablas se construye sobre esa idea simple, repetida muchas veces.

### 2.2. Tipos de relación

En esta app aparecen dos tipos:

| Tipo | Significado | Cómo se implementa |
|---|---|---|
| **Uno a muchos (1:N)** | Un A tiene muchos B, pero cada B pertenece a un solo A. | La FK va en el lado "muchos" (B). |
| **Muchos a muchos (N:M)** | Ambos lados son "muchos". | Se crea una **tabla intermedia** con dos FKs, una a cada lado. |

**Ejemplos en esta app:**

- Un `restaurante` tiene muchas `mesa`, pero cada `mesa` pertenece a un solo `restaurante`. **1:N.** Mesa guarda `restaurante_id`.
- Un `pedido` puede tener muchos `producto` (pides 2 cañas + 1 tortilla), y un `producto` puede estar en muchos `pedido` (la caña se pide cientos de veces). **N:M.** Se resuelve con la tabla intermedia `linea_pedido`, que tiene FK a `pedido` Y a `producto`.

Una sola FK no puede expresar N:M: solo guarda una conexión. Por eso N:M obliga a una tabla intermedia.

### 2.3. La jerarquía completa: todo cuelga de `restaurante`

El diseño es multi-tenant: una sola base de datos para todos los bares. Cada bar es un subconjunto identificado por `restaurante_id`. Todas las tablas cuelgan del restaurante, directa o indirectamente:

```
                       ┌──────────────────────┐
                       │    RESTAURANTE       │   ← raíz multi-tenant
                       │      (id PK)         │
                       └──────────┬───────────┘
                                  │
        ┌──────────────────┬──────┴──────┬──────────────────┐
        │                  │             │                  │
   ┌────▼─────┐      ┌─────▼─────┐  ┌────▼──────┐
   │ USUARIO  │      │   MESA    │  │ CATEGORIA │
   │ rest_id  │      │  rest_id  │  │  rest_id  │
   └──────────┘      └─────┬─────┘  └────┬──────┘
                           │             │
                     ┌─────▼─────┐  ┌────▼──────┐
                     │SESION_MESA│  │ PRODUCTO  │
                     │  mesa_id  │  │ categ_id  │
                     └─────┬─────┘  └────┬──────┘
                           │             │
                     ┌─────▼─────┐       │
                     │  PEDIDO   │       │
                     │ sesion_id │       │
                     └─────┬─────┘       │
                           │             │
                     ┌─────▼─────────────▼─────┐
                     │      LINEA_PEDIDO       │   ← tabla intermedia (N:M)
                     │ pedido_id, producto_id  │
                     └─────────────────────────┘
```

Esa imagen es el plano mental del sistema. Conviene tenerla a la vista mientras se lee el resto del documento.

### 2.4. Relación a relación, explicada

Cada flecha del diagrama es una relación. Veámoslas una a una:

**Restaurante → Usuario.** Un restaurante tiene muchos usuarios (su dueño y sus meseros), pero cada usuario pertenece a un solo restaurante. La tabla `usuario` tiene la columna `restaurante_id` que guarda el id del bar al que pertenece. Si vale `1`, ese usuario es del Bar O Lar.

**Restaurante → Mesa.** Un restaurante tiene muchas mesas, cada mesa pertenece a un solo restaurante. `mesa.restaurante_id` apunta al `id` del restaurante.

**Restaurante → Categoría.** Cada bar tiene su propia carta. `categoria` también lleva `restaurante_id`. Cuando el dueño del Bar O Lar crea "Bebidas", esa fila va con `restaurante_id = 1`.

**Categoría → Producto.** Una categoría tiene muchos productos (Bebidas → Caña, Café, Agua). Cada producto pertenece a una categoría. `producto.categoria_id` apunta al `id` de la categoría. Producto **no** guarda `restaurante_id` directamente — se deduce a través de la categoría (ver 2.5).

**Mesa → Sesion_mesa.** Cada vez que un grupo de clientes ocupa una mesa, se abre una "sesión" en esa mesa. Una mesa puede tener muchas sesiones a lo largo del tiempo (grupos que rotan). Y **puede tener varias sesiones abiertas a la vez** (cuando alguien quiere pagar aparte). `sesion_mesa.mesa_id` apunta al `id` de la mesa.

**Sesion_mesa → Pedido.** Dentro de una sesión, los clientes (o el mesero) pueden hacer varios pedidos (una primera ronda, luego otra). Cada pedido pertenece a una sesión. `pedido.sesion_mesa_id` apunta al `id` de la sesión.

**Pedido → Linea_pedido.** Cada pedido se compone de varias líneas (cada línea es un producto pedido). Un pedido tiene muchas líneas, cada línea pertenece a un solo pedido. `linea_pedido.pedido_id` apunta al `id` del pedido.

**Producto → Linea_pedido.** Cada línea hace referencia al producto que se pidió. `linea_pedido.producto_id` apunta al `id` del producto.

Esto último es lo que cierra la relación muchos-a-muchos entre pedido y producto: `linea_pedido` tiene DOS claves foráneas, una a `pedido` y otra a `producto`, y cada fila representa una conexión concreta — "este producto está en este pedido, con esta cantidad y este precio".

### 2.5. Por qué algunas tablas NO tienen `restaurante_id`

Mirando la jerarquía, `usuario`, `mesa` y `categoria` tienen `restaurante_id` directo. Pero `producto`, `sesion_mesa`, `pedido` y `linea_pedido` **no**. ¿Cómo se sabe a qué bar pertenecen?

**A través de la cadena de claves foráneas.** Cada una pertenece al restaurante de su "padre":

- Un `producto` pertenece al restaurante de su categoría: `producto → categoria → restaurante`.
- Una `sesion_mesa` pertenece al restaurante de su mesa: `sesion_mesa → mesa → restaurante`.
- Un `pedido` pertenece al restaurante de su sesión: `pedido → sesion_mesa → mesa → restaurante`.
- Una `linea_pedido` pertenece al restaurante por la cadena más larga: `linea_pedido → pedido → sesion_mesa → mesa → restaurante`.

**¿Por qué no se duplica `restaurante_id` en todas las tablas para evitar JOINs?**

Porque la información duplicada se puede desincronizar. La regla del diseño relacional es: **no dupliques información que se puede deducir**. Si `producto` guardara `restaurante_id` además del `categoria_id`, podrían darse inconsistencias (alguien cambia la categoría y olvida el `restaurante_id`). Manteniendo el dato en un solo sitio, no hay posibilidad de inconsistencia.

El coste son JOINs más largos al consultar, pero con índices en las FKs (los hay, ver sección 4) PostgreSQL los resuelve muy rápido.

### 2.6. Cómo se "leen" los datos cruzando tablas: el JOIN

Cuando un dato vive repartido entre varias tablas, para verlo junto se usa `JOIN`. Es la operación más importante de una base relacional.

La fórmula es siempre la misma:

```sql
SELECT campos...
FROM tabla1
JOIN tabla2 ON tabla1.fk = tabla2.id
WHERE condiciones;
```

El `JOIN ... ON ...` dice "empareja cada fila de tabla1 con la fila de tabla2 cuyo id coincide con la FK". Es activar la relación que se definió con la clave foránea, pero ahora para leer datos.

**Ejemplo con datos reales.** "Dame los productos del Bar O Lar":

```sql
SELECT producto.nombre_es, producto.precio_centimos
FROM producto
JOIN categoria ON producto.categoria_id = categoria.id
WHERE categoria.restaurante_id = 1;
```

Lo que pasa internamente:
1. Empieza con la tabla `producto`.
2. Para cada producto, busca su categoría emparejando `producto.categoria_id` con `categoria.id`.
3. Filtra y se queda solo con los productos cuya categoría tenga `restaurante_id = 1`.
4. Devuelve nombre y precio.

Cuando hay que cruzar más tablas (3, 4 o más), se encadenan los JOINs uno tras otro. Cada flecha del diagrama de la sección 2.3 puede convertirse en un JOIN si la consulta lo necesita.

**Importante para seguridad multi-tenant:** PostgreSQL **no filtra automáticamente** por restaurante. Si una consulta del backend olvida el `WHERE restaurante_id = X` (o el equivalente vía JOIN), devolverá datos mezclados de varios bares. El aislamiento es responsabilidad del backend, no de la base. Conviene centralizar el filtrado en una capa común (middleware) para no depender de que cada query individual lo respete.

### 2.7. Resumen visual de todas las claves foráneas

Lista completa de FKs del sistema, para referencia rápida:

| Tabla | Columna FK | Apunta a | Notas |
|---|---|---|---|
| `usuario` | `restaurante_id` | `restaurante(id)` | ON DELETE CASCADE |
| `mesa` | `restaurante_id` | `restaurante(id)` | ON DELETE CASCADE |
| `categoria` | `restaurante_id` | `restaurante(id)` | ON DELETE CASCADE |
| `producto` | `categoria_id` | `categoria(id)` | ON DELETE RESTRICT |
| `sesion_mesa` | `mesa_id` | `mesa(id)` | ON DELETE RESTRICT |
| `sesion_mesa` | `cerrada_por_mesero_id` | `usuario(id)` | Opcional, ON DELETE SET NULL |
| `pedido` | `sesion_mesa_id` | `sesion_mesa(id)` | ON DELETE RESTRICT |
| `pedido` | `mesero_id` | `usuario(id)` | Opcional, ON DELETE SET NULL |
| `linea_pedido` | `pedido_id` | `pedido(id)` | ON DELETE CASCADE |
| `linea_pedido` | `producto_id` | `producto(id)` | ON DELETE RESTRICT |

10 FKs en total. Las marcadas como opcionales pueden quedar a NULL (por ejemplo, `pedido.mesero_id` solo se rellena si el pedido lo tomó un mesero; si vino del cliente vía QR, queda vacío).

---

## 3. DECISIONES DE DISEÑO IMPORTANTES

### Multi-tenant compartido
Una sola base de datos para todos los bares. Cada bar es un subconjunto identificado por `restaurante_id`. El backend debe filtrar disciplinadamente — el aislamiento NO lo hace la base sola.

### Precios en céntimos enteros
1,50 € se guarda como `150`. Evita errores de redondeo de coma flotante en cálculos financieros. Al mostrar al usuario, dividir entre 100.

### Precio congelado en `linea_pedido`
El precio del producto se **copia** en cada línea cuando se crea. Así, si el dueño sube el precio mañana, los pedidos ya cerrados conservan su precio original. Integridad histórica.

### Token QR único en lugar del número de mesa
El QR contiene un `token_qr` aleatorio (no el número de mesa visible) para evitar manipulación de URL. Si alguien cambia el número en la URL, no entra a otra mesa.

### `password_hash`, nunca contraseñas en claro
La columna se llama así explícitamente. El hasheo lo hace el backend antes de insertar.

### Capa intermedia `sesion_mesa`
Pedido no apunta a mesa directamente, sino a una "sesión" (unidad de pago). Esto permite:
- Grupos distintos que rotan en la misma mesa queden separados.
- Varios comensales paguen por separado abriendo cada uno su sesión.
- Una mesa puede tener varias sesiones abiertas al mismo tiempo.

### Multi-idioma con columnas
Cada texto traducible tiene columnas `nombre_es`, `nombre_en`, `nombre_fr` (y `descripcion_es/en/fr` en producto). Solo `_es` es obligatorio.

### Restricciones CHECK
Campos con valores cerrados (estado, origen, rol) tienen `CHECK` para que la base rechace valores inventados por error de tipeo.

### Índices en todas las claves foráneas
Para rendimiento en consultas con JOIN.

### Comportamiento ON DELETE
- `CASCADE` donde la "muerte" debe propagarse (borrar restaurante → se van usuarios, mesas, categorías).
- `RESTRICT` donde se protege el histórico (no se puede borrar un producto si está en pedidos).
- `SET NULL` donde la referencia es opcional (si se borra un mesero, los pedidos que tomó conservan su historia sin la referencia).

---

## 4. ESQUEMA SQL COMPLETO

```sql
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

CREATE TABLE mesa (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    restaurante_id  BIGINT      NOT NULL REFERENCES restaurante(id) ON DELETE CASCADE,
    numero          TEXT        NOT NULL,
    token_qr        TEXT        NOT NULL UNIQUE,
    activa          BOOLEAN     NOT NULL DEFAULT true,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_mesa_restaurante ON mesa(restaurante_id);

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
```

---

## 5. CONSULTAS PRINCIPALES YA VALIDADAS

Estas consultas están probadas y funcionan. Cada una corresponde a un endpoint del backend.

### Consulta 1 — Cargar la carta del bar
**Cuándo se usa:** cliente escanea el QR, app pide la carta para mostrar.
**Tablas que cruza:** `categoria` + `producto`.

```sql
SELECT categoria.nombre_es      AS categoria,
       producto.nombre_es       AS producto,
       producto.precio_centimos AS precio
FROM categoria
JOIN producto ON categoria.id = producto.categoria_id
WHERE categoria.restaurante_id = 1
  AND producto.disponible = true
ORDER BY categoria.orden, producto.nombre_es;
```

### Consulta 2 — Pedidos activos para cocina
**Cuándo se usa:** tablero de cocina hace polling cada 3-5s.
**Tablas que cruza:** `pedido` + `sesion_mesa` + `mesa`.

```sql
SELECT pedido.id              AS pedido_id,
       mesa.numero            AS mesa,
       pedido.estado          AS estado,
       pedido.creado_en       AS hora
FROM pedido
JOIN sesion_mesa ON pedido.sesion_mesa_id = sesion_mesa.id
JOIN mesa        ON sesion_mesa.mesa_id   = mesa.id
WHERE mesa.restaurante_id = 1
  AND pedido.estado IN ('recibido', 'en_preparacion')
ORDER BY pedido.creado_en;
```

### Consulta 3 — Total de una sesión
**Cuándo se usa:** mesero o cliente quiere saber cuánto se debe pagar.
**Tablas que cruza:** `linea_pedido` + `pedido` (para filtrar por sesión).

```sql
SELECT SUM(linea_pedido.cantidad * linea_pedido.precio_unitario_centimos) AS total_centimos
FROM linea_pedido
JOIN pedido ON linea_pedido.pedido_id = pedido.id
WHERE pedido.sesion_mesa_id = 1;
```

### Consulta 4 — Detalle completo de un pedido
**Cuándo se usa:** ver el ticket de un pedido con nombres legibles.
**Tablas que cruza:** `linea_pedido` + `producto` + `pedido` + `sesion_mesa` + `mesa` (5 tablas, 4 JOINs).

```sql
SELECT producto.nombre_es     AS producto,
       linea_pedido.cantidad  AS cantidad,
       linea_pedido.precio_unitario_centimos AS precio_unidad,
       linea_pedido.nota      AS nota,
       mesa.numero            AS mesa
FROM linea_pedido
JOIN producto    ON linea_pedido.producto_id = producto.id
JOIN pedido      ON linea_pedido.pedido_id = pedido.id
JOIN sesion_mesa ON pedido.sesion_mesa_id = sesion_mesa.id
JOIN mesa        ON sesion_mesa.mesa_id = mesa.id
WHERE pedido.id = 1;
```

---

## 6. FLUJO DE UN PEDIDO (DE PRINCIPIO A FIN)

Cuando un cliente escanea y pide, el sistema ejecuta esta secuencia:

```
1. Cliente escanea el QR de la mesa.
   → SELECT mesa WHERE token_qr = '...'
     (obtiene mesa_id y restaurante_id)

2. App carga la carta.
   → SELECT categoria JOIN producto WHERE restaurante_id
     (consulta 1 del punto 5)

3. ¿Hay sesión abierta en la mesa?
   → SELECT sesion_mesa WHERE mesa_id = X AND estado = 'abierta'

   3a. Si NO hay:
      → INSERT sesion_mesa (mesa_id, abierta_por='automatica')

   3b. Si SÍ hay:
      → Usar la existente, O abrir una nueva si el cliente quiere pagar aparte.

4. Cliente confirma el pedido.
   → INSERT pedido (sesion_mesa_id, origen='cliente', estado='recibido')

5. Por cada producto del carrito:
   → INSERT linea_pedido (pedido_id, producto_id, cantidad, precio_copiado)

6. Cocina hace polling.
   → SELECT pedidos activos JOIN sesion_mesa JOIN mesa
     (consulta 2 del punto 5)
   → El pedido aparece en el tablero.

7. Cocina avanza el estado.
   → UPDATE pedido SET estado = 'en_preparacion'
   → ... más tarde ...
   → UPDATE pedido SET estado = 'servido'

8. Cliente paga al mesero. Mesero cierra la sesión.
   → UPDATE sesion_mesa
        SET estado = 'cerrada',
            cerrada_en = now(),
            cerrada_por_mesero_id = X
```

El flujo del **comandero del mesero** es idéntico desde el paso 4, solo que `origen = 'mesero'` y se rellena `mesero_id`.

---

## 7. LO QUE QUEDA POR HACER

### Pendiente inmediato (próximas sesiones)
- **Mapear consultas a endpoints de la API.** Decidir URLs y verbos HTTP (GET, POST, PATCH) para cada acción.
- **Definir las INSERT/UPDATE que faltan** como endpoints: crear pedido + líneas, cerrar sesión, marcar línea como no disponible, etc.
- **Montar el backend** (FastAPI / Laravel / Symfony — por decidir).
- **Implementar autenticación** (login para dueño y meseros, con tokens).

### A medio plazo
- **Frontend del cliente** (carta + carrito + estado del pedido).
- **Frontend de cocina** (tablero con polling).
- **Frontend del mesero** (comandero).
- **Frontend del dueño** (gestión de carta, mesas, QR, meseros).
- **Despliegue:** dominio, HTTPS, hosting.

### Pendiente de validar
- **Adopción del QR por la clientela** del bar piloto (hipótesis arriesgada).
- **Cuánta de la clientela** prefiere pedir vía QR vs vía mesero.
- **Si los meseros usan su móvil personal** o necesitan un dispositivo del bar.

### Fuera del MVP (versiones futuras)
- Pasarela de pago y facturación (implica cumplimiento Verifactu).
- Descripciones de platos traducidas con IA.
- Analítica del negocio (qué se vende, horas pico, comparativas).
- Modo offline del comandero.
- Adaptación a retail (talla/color).
- Adaptación a servicios (citas).

---

## 8. DATOS DE PRUEBA CARGADOS

La base de datos tiene cargado un bar de ejemplo ("Bar O Lar") con:

- 1 restaurante (id = 1, slug 'bar-o-lar', idiomas ES/EN/FR).
- 2 usuarios: dueño (id=1, jefe@barolar.es) y mesero Pedro (id=2, pedro@barolar.es).
- 2 mesas (Mesa 1 y Mesa 2) con sus tokens QR.
- 2 categorías (Bebidas, Tapas) en 3 idiomas.
- 5 productos (Caña, Café, Agua, Tortilla, Croquetas) con precios en céntimos.
- 2 sesiones abiertas, una por cada mesa.
- 2 pedidos (uno por cliente vía QR, otro por mesero vía comandero).
- 4 líneas de pedido en total.

Sirve como dataset realista para desarrollar y probar el backend.

---

## 9. AUTENTICACIÓN — DECISIÓN PENDIENTE

Hay tres tipos de "usuario" en el sistema, y cada uno necesita un tratamiento distinto:

| Usuario | Necesita login | Cómo se identifica |
|---|---|---|
| **Cliente (QR)** | NO | Por el `token_qr` de la mesa y la `sesion_mesa` activa. Sin registro, sin contraseña. |
| **Mesero** | SÍ | Login con email + password. Sesión que dura el turno. |
| **Dueño** | SÍ | Login con email + password. Sesión normal. |

El flujo del cliente NO requiere autenticación — el `token_qr` ya es prueba suficiente de que la persona está físicamente sentada en esa mesa, y no se piden datos personales. Esto simplifica mucho y esquiva la mayor parte del RGPD.

Para **mesero y dueño**, hay que elegir mecanismo de sesión. Tres opciones realistas:

### Opción A — JWT en cabecera `Authorization: Bearer` (recomendada)
- El backend genera un JWT (JSON Web Token) al hacer login.
- El frontend lo guarda y lo envía en cada petición en la cabecera `Authorization: Bearer <token>`.
- **Pros:** stateless (backend no guarda sesiones), funciona perfecto en cualquier frontend (web, móvil), encaja bien con APIs REST.
- **Contras:** si se guarda en `localStorage`, vulnerable a XSS. Mitigación: tokens con expiración corta (15-30 min) + refresh token, y sanitización rigurosa contra XSS.

### Opción B — JWT en cookie HttpOnly
- Mismo JWT, pero el backend lo mete en una cookie con flag `HttpOnly`.
- El navegador la envía automáticamente, el JavaScript no puede leerla.
- **Pros:** protegido contra XSS (el JS malicioso no puede leer la cookie).
- **Contras:** vulnerable a CSRF (mitigable con `SameSite=Strict` y/o tokens CSRF). Más complejo si en el futuro hay una app móvil nativa.

### Opción C — Cookie de sesión clásica (server-side session)
- El backend mantiene una sesión real en su lado (en memoria o en base).
- El navegador recibe una cookie con un id de sesión.
- **Pros:** simple, robusto, controlable desde el backend (se puede invalidar inmediatamente).
- **Contras:** backend con estado (complica escalado horizontal), peor encaje con APIs REST puras, no apto si en el futuro hay app móvil nativa.

### Recomendación
**Opción A (JWT en cabecera)** para esta app, por simplicidad y porque encaja con la arquitectura prevista (varios frontends web independientes consumiendo la misma API). Si la preocupación por XSS pesa, **Opción B (cookie HttpOnly)** es alternativa válida.

**Decisión pendiente:** la final la tomamos cuando empecemos el backend.

### Independientemente del mecanismo

Sea cual sea la opción elegida, estas reglas se mantienen:

- Contraseñas siempre **hasheadas** antes de guardar (bcrypt o argon2). La columna `password_hash` ya lo refleja.
- **Sesiones con expiración** (no sesiones eternas).
- **Filtrado por `restaurante_id` en cada petición autenticada** — el token debe incluir a qué bar pertenece el usuario, y el backend lo usa como filtro automático en todas las consultas. Esto es lo que garantiza el aislamiento multi-tenant.
- **HTTPS obligatorio en producción** (sin HTTPS cualquier mecanismo se rompe).

---

## 10. NOTAS DE CONTEXTO

- El producto está pensado para bares pequeños y medianos con alta rotación. Objetivo inicial: O Barco de Valdeorras y comarca.
- No compite con TPV completos (MyChefTool, Qamarero); se posiciona como complemento ligero y barato con soporte local.
- El MVP excluye pago/facturación a propósito para evitar el cumplimiento de Verifactu (normativa española de software de facturación, obligatoria desde 2027 para autónomos y pymes).
- El multi-idioma (ES/EN/FR) está pensado para la zona del Camino y la frontera con Portugal.
