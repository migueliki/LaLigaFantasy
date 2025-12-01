<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentación - Proyecto Laliga Fantasy</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #5eb2eaff;
        }
        .container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #e74c3c; /* Color rojo similar al logo */
            padding-bottom: 10px;
        }
        h2 {
            color: #2980b9;
            margin-top: 30px;
        }
        p {
            margin-bottom: 15px;
        }
        ul {
            margin-bottom: 20px;
        }
        code {
            background-color: #a6deecff;
            padding: 2px 5px;
            border-radius: 4px;
            font-family: monospace;
            color: #d63384;
        }
        /* Estilos para los marcadores de imagen */
        .img-placeholder {
            background-color: #e9ecef;
            border: 2px dashed #adb5bd;
            color: #495057;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            border-radius: 8px;
        }
        .img-placeholder span {
            display: block;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .img-instruction {
            font-size: 0.9em;
            color: #6c757d;
        }
        footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Proyecto Laliga Fantasy</h1>
    </header>

    <section id="introduccion">
        <h2>Introducción</h2>
        <p>La aplicación Laliga Fantasy es una plataforma web diseñada para gestionar y visualizar información sobre jugadores. Es un entorno donde los usuarios pueden interactuar con datos reales y personalizados de la liga.</p>
        
       
          <img src="../images/documentacion/image6.png" alt="Logo Fantasy" width="100%">
        
    </section>

    <section id="tecnologias">
        <h2>¿Cómo funciona?</h2>
        <p>Nuestro proyecto funciona mayormente por código <strong>PHP</strong> y <strong>HTML</strong>, aparte de usar <strong>CSS</strong> para los diseños de la app web y cada página.</p>
        <p>Usamos también una base de datos que almacena los datos de todos los jugadores actuales de la liga española y que almacena los usuarios registrados.</p>
        
        <h3>Estructura del Proyecto</h3>
        <p>El proyecto cuenta con una estructura de archivos organizada en carpetas como <code>app</code>, <code>css</code> y <code>pages</code>, incluyendo archivos clave como:</p>
        <ul>
            <li><code>conexion.php</code> (Base de datos)</li>
            <li><code>delete.php</code>, <code>insert.php</code>, <code>modify.php</code> (Gestión de datos)</li>
            <li><code>login.php</code>, <code>register.php</code> (Autenticación)</li>
        </ul>

       <img src="../images/documentacion/image3.png" alt="Logo Fantasy" width="50%">
    </section>

    <section id="flujo-usuario">
        <h2>Registro e Inicio de Sesión</h2>
        <p>Al entrar en la página, lo primero que se hace es registrarte y posteriormente iniciar sesión. El sistema cuenta con formularios seguros para:</p>
        <ul>
            <li><strong>Registrar usuario:</strong> Nombre, Email y Contraseña.</li>
            <li><strong>Iniciar Sesión:</strong> Nombre y Contraseña.</li>
        </ul>

        <img src="../images/documentacion/image4.png" alt="Logo Fantasy" width="100%">
    </section>

    <section id="plantilla">
        <h2>Gestión de Plantilla</h2>
        <p>Entre nuestros apartados, tenemos una sección donde puedes crear tu propia plantilla con jugadores personalizados, poniendo el nombre que tú quieras, la posición, dorsal y equipo que quieras.</p>
        
        <p>Opciones disponibles:</p>
        <ul>
            <li>Añadir jugadores reales de nuestra lista (Liga Española).</li>
            <li>¡Puedes ponerte a ti mismo y simular que eres un jugador del Real Madrid o del Barça!</li>
            <li>Inventarte el equipo que tú quieras.</li>
            <li>Modificar (editar datos) o eliminar jugadores.</li>
        </ul>

       <img src="../images/documentacion/image1.png" alt="Logo Fantasy" width="100%">
    </section>

    <section id="lista-jugadores">
        <h2>Lista de Jugadores y Búsqueda</h2>
        <p>En la sección de "Lista de jugadores" nos encontraremos a todos los jugadores de la liga.</p>
        
        <p>El sistema incluye un potente buscador que permite:</p>
        <ul>
            <li>Buscar un jugador en concreto por nombre.</li>
            <li>Poner un equipo en concreto para que salgan todos los jugadores de ese equipo.</li>
        </ul>

       <img src="../images/documentacion/image5.png" alt="Logo Fantasy" width="100%">
       <img src="../images/documentacion/image7.png" alt="Logo Fantasy" width="100%">
    </section>

    <section id="conclusion">
        <h2>Conclusión</h2>
        <p>La página se trata de un gestor de jugadores de fútbol, en el que tienes una lista donde ver todos los jugadores de la liga española.</p>
        <p>En el apartado de plantillas, puedes añadir, modificar, editar o eliminar jugadores para tu equipo personalizado. Recuerda que puedes añadir jugadores reales, inventados, o añadirte a ti mismo o a cualquier amigo, conocido o famoso.</p>
    </section>

    <footer>
        <p>&copy; 2024 Proyecto IAW - Laliga Fantasy</p>
    </footer>
</div>

</body>
</html>