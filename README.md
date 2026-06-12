# App de pedidos QR para bares

App SaaS multi-tenant para que los clientes de un bar pidan desde el móvil escaneando un QR en la mesa, y para que los meseros tomen comanda desde su propio móvil. Los pedidos caen directos en una pantalla de cocina en tiempo real.

**Estado actual:** diseño de datos completo. Esquema implementado en PostgreSQL. Consultas principales validadas. Pendiente: backend (API) y frontend.

## Stack

- **Base de datos:** PostgreSQL
- **Backend:** por decidir (PHP o Python)
- **Frontend:** web (sin app instalable)

## Estructura del repo

```
docs/
  diseno-final.md                       Visión del producto y decisiones de negocio.
  documentacion-tecnica-app-bares.md    Documentación técnica completa.
                                        ← empezar por aquí para entender el sistema.
sql/
  esquema-final.sql                     Script para crear la base de datos desde cero.
```

## Cómo arrancar la base de datos en local

1. Tener PostgreSQL instalado.
2. Crear una base de datos llamada `pedidos_bares`.
3. Ejecutar `sql/esquema-final.sql` contra esa base.

Después, opcionalmente, cargar el dataset de prueba del Bar O Lar (los INSERTs están descritos en la sección 8 de la documentación técnica).

## Por dónde empezar a leer

Si llegas al proyecto y quieres entenderlo, **lee `docs/documentacion-tecnica-app-bares.md` en este orden**:

1. Sección 1 — qué hace el producto.
2. Sección 2 — cómo se relacionan las tablas (la más importante).
3. Sección 5 — las consultas principales ya validadas.
4. Sección 6 — el flujo de un pedido de principio a fin.

El resto sirve de referencia.

## Lo que queda por hacer

Ver sección 7 de la documentación técnica.
