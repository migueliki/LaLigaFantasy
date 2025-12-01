# Proyecto_IAW
# 🚀 Configuración e Introducción al Proyecto

Este `README` describe los pasos de configuración y las funcionalidades principales de la aplicación web.

---

## 💻 Requisitos y Configuración Inicial

Para empezar a trabajar con el proyecto, necesitarás instalar los siguientes componentes:

1.  **Instalación de la pila LAMP (MySQL, PHP, Apache2):**
    * Lo primero de todo tendremos que tener instalado php, mysql y la extensión de phpmyadmin con el siguiente comando:
        ```bash
        sudo apt install mysql-server php php-mysql phpmyadmin apache2
        ```

2.  **Configuración de la Base de Datos (MySQL):**
    * Luego en mysql crearemos un usuario llamado **'admin'** y con la contraseña **'1234'**.

3.  **Configuración del Entorno de Desarrollo (Visual Studio Code):**
    * Después en nuestro **Visual Studio** instalaremos la siguiente extensión para **PHP** (Asegúrate de buscar la extensión de soporte para PHP más adecuada).
     /home/miguel/Imágenes/readme/extension.jpg

---

## ▶️ Ejecución y Acceso a la Web

Para arrancar la página web:

* Tendremos que irnos a `/app/index.php`.
* Abrir el servicio desde el editor **Visual Studio Code** (o tu servidor web local).

---

## ✨ Funcionalidades Principales

Una vez se nos abre nuestra página web en el navegador, veremos:

1.  **Registro e Inicio de Sesión:**
    * Una vez nos registramos y logueamos accederemos a la página de `inicio.php` donde podremos ver un simple **menú de navegación**.

2.  **Personalización de Color de Fondo:**
    * Si nos damos cuenta podemos visualizar **dos botones** en la esquina inferior derecha.
    * Al pulsarlos podremos **establecer el color de fondo** de la página, el cual se almacena en una **cookie**.

3.  **Lista de Jugadores:**
    * En el primer enlace a **“lista de jugadores”** veremos un despliegue con todos los jugadores registrados en su correspondiente base de datos.
    * Contaremos con un **buscador** que filtrará por **nombre y equipo**.
    * Si *escroleamos* al final de la página podremos ver su **paginación** para los resultados.

4.  **Gestión de Plantilla (CRUD):**
    * Por último, en la sección de **"plantilla"** tendremos un **registro de jugadores** para nuestro equipo.
    * El cual también podremos **editar y eliminar** los registros.


