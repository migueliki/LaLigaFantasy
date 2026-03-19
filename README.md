# LaLiga Fantasy

Aplicación web estilo fantasy de LaLiga desarrollada en PHP + MySQL.
Permite registrarse, crear o unirse a ligas privadas, gestionar plantilla, comprar/vender jugadores en mercado y consultar equipos, calendario y noticias.

## Características principales

- Autenticación de usuarios (login y registro).
- Gestión de ligas privadas con código de invitación.
- Clasificación por liga con puntos por jornada y acumulados.
- Plantilla fantasy con formaciones, titulares/suplentes y capitán.
- Mercado de jugadores (comprar/vender) con control de propiedad.
- Consulta de equipos, plantillas reales y entrenadores.
- Calendario de jornadas y sincronización de datos en vivo.
- Noticias cacheadas y selector de tema visual.
- Protección CSRF en formularios críticos.

## Stack técnico

- PHP (PDO para acceso a datos)
- MySQL / MariaDB
- HTML, CSS y JavaScript vanilla
- Entorno recomendado: XAMPP en Windows

## Estructura del proyecto

```text
app/
	index.php              # Login/registro
	inicio.php             # Dashboard principal y ranking de ligas
	pages/
		equipos.php          # Equipos, entrenador y jugadores
		calendario.php       # Jornadas/partidos
		plantilla.php        # Gestión de tu plantilla fantasy
		mercado.php          # Mercado de fichajes
		noticias.php         # Noticias
	lib/
		fantasy_bootstrap.php
		fantasy_live.php
		league_service.php
	css/
	js/
	images/
database/
	fantasy.sql            # Esquema + datos iniciales
```

## Requisitos

- PHP 8.0 o superior
- MySQL 8+ o MariaDB compatible
- Apache (o servidor equivalente)
- Extensión PDO MySQL habilitada

## Instalación local (XAMPP)

1. Inicia Apache y MySQL desde el panel de XAMPP.
2. Crea la base de datos importando `database/fantasy.sql`.
	 - Puedes hacerlo desde phpMyAdmin: Importar -> seleccionar `database/fantasy.sql`.
3. Revisa credenciales en `app/conexion.php`.
	 - La app usa por defecto:
		 - Base de datos: `fantasy`
		 - Usuario: `admin`
		 - Password: `fantasyASIR25`
		 - Puerto: `3306`
4. Asegura la ruta local esperada por `BASE_URL`.
	 - En localhost, `app/config.php` define `BASE_URL = '/app'`.
	 - Esto implica servir la aplicación bajo la ruta `/app`.
5. Abre en el navegador:
	 - `http://localhost/app/index.php`

## Configuración importante

### Base URL

En `app/config.php` se detecta automáticamente el entorno:

- Localhost -> `BASE_URL = '/app'`
- Producción -> `BASE_URL = ''`

Si tu estructura local no coincide con `/app`, ajusta esa lógica para evitar rutas rotas en CSS/JS/imagenes.

### Base de datos

La conexión está en `app/conexion.php` y selecciona host según entorno:

- Local: `localhost`
- Remoto: instancia RDS

Para desarrollo local, verifica usuario/contraseña y permisos del usuario MySQL.

## Flujo funcional (resumen)

1. Usuario se registra o inicia sesión.
2. Crea una liga o se une con código.
3. Gestiona su plantilla y mercado.
4. Consulta jornadas, resultados y puntos fantasy.
5. Revisa clasificación dentro de cada liga.

## Seguridad y buenas prácticas

- Tokens CSRF en formularios sensibles (`app/csrf.php`).
- Control de sesión y timeout de inactividad.
- Uso de consultas preparadas PDO en operaciones críticas.

Recomendaciones para producción:

- No mantener credenciales reales en texto plano dentro del repositorio.
- Mover secretos a variables de entorno.
- Desactivar `display_errors` en producción.
- Configurar HTTPS y cookies de sesión seguras.

## Troubleshooting rápido

- Error de conexión DB:
	- Revisa `app/conexion.php`, usuario/clave/host/puerto.
	- Verifica que MySQL esté iniciado y la BD `fantasy` exista.
- Estilos o imágenes no cargan:
	- Revisa valor de `BASE_URL` en `app/config.php`.
	- Confirma que la app esté sirviéndose bajo `/app` en local.
- Redirecciones al login:
	- La sesión pudo expirar (timeout).

## Estado del proyecto

Proyecto funcional orientado a entorno académico/demo, con módulos de fantasy, ligas y sincronización de jornada ya integrados.
