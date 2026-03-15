CREATE DATABASE fantasy;
USE fantasy;

CREATE TABLE equipos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    ciudad VARCHAR(100),
    fundacion INT,  -- year no soporta años debajo de 1901
    estadio VARCHAR(100)
);


CREATE TABLE register (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE usuarios_equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    nombre_equipo VARCHAR(100) NOT NULL,
    saldo DECIMAL(12,2) NOT NULL DEFAULT 100000000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES register(id) ON DELETE CASCADE
);

CREATE TABLE usuarios_jugadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    jugador_id INT NOT NULL,
    es_titular BOOLEAN DEFAULT FALSE,
    slot_titular TINYINT UNSIGNED DEFAULT NULL,
    es_capitan BOOLEAN DEFAULT FALSE,
    precio_compra DECIMAL(12,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_jugador (usuario_id, jugador_id),
    UNIQUE KEY uq_propiedad_jugador (jugador_id),
    FOREIGN KEY (usuario_id) REFERENCES register(id) ON DELETE CASCADE
);

CREATE TABLE mercado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT NOT NULL UNIQUE,
    precio DECIMAL(12,2) NOT NULL,
    disponible BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_publicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    jugador_id INT NOT NULL,
    tipo ENUM('compra', 'venta') NOT NULL,
    precio DECIMAL(12,2) NOT NULL,
    jornada INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES register(id) ON DELETE CASCADE
);

CREATE TABLE puntos_jornada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    jornada INT NOT NULL,
    puntos_jornada INT NOT NULL DEFAULT 0,
    puntos_totales INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_jornada (usuario_id, jornada),
    FOREIGN KEY (usuario_id) REFERENCES register(id) ON DELETE CASCADE
);

CREATE TABLE ligas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo_invitacion VARCHAR(20) NOT NULL UNIQUE,
    creador_usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creador_usuario_id) REFERENCES register(id) ON DELETE CASCADE
);

CREATE TABLE ligas_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    liga_id INT NOT NULL,
    usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_liga_usuario (liga_id, usuario_id),
    FOREIGN KEY (liga_id) REFERENCES ligas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES register(id) ON DELETE CASCADE
);

CREATE TABLE jugadores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    edad INT,
    nacionalidad VARCHAR(50),
    equipo_id INT,
    media_fifa INT,
    posicion VARCHAR(50),
    dorsal INT,
    es_titular BOOLEAN DEFAULT TRUE,  -- TRUE=titular, FALSE=suplente
    lesional BOOLEAN DEFAULT FALSE,   -- Si está lesionado
    FOREIGN KEY (equipo_id) REFERENCES equipos(id)
);

CREATE TABLE plantilla (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    equipo VARCHAR(100) NOT NULL,
    posicion VARCHAR(50),
    nacionalidad VARCHAR(50),
    dorsal INT
);

CREATE TABLE entrenadores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    edad INT,
    nacionalidad VARCHAR(50),
    equipo_id INT UNIQUE,  
	estilo_juego VARCHAR(100),  -- Ej: "Posesión", "Contraataque"
    FOREIGN KEY (equipo_id) REFERENCES equipos(id)
);

CREATE TABLE partidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jornada INT NOT NULL,
    equipo_local_id INT NOT NULL,
    equipo_visitante_id INT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    goles_local INT DEFAULT NULL,
    goles_visitante INT DEFAULT NULL,
    api_event_id VARCHAR(32) DEFAULT NULL,
    estado_partido VARCHAR(50) NOT NULL DEFAULT 'Programado',
    ultima_sync DATETIME DEFAULT NULL,
    UNIQUE KEY uq_partidos_api_event_id (api_event_id),
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id),
    FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id)
);

CREATE TABLE fantasy_player_match_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partido_id INT NOT NULL,
    jugador_id INT NOT NULL,
    api_player_id VARCHAR(32) DEFAULT NULL,
    nombre_api VARCHAR(120) DEFAULT NULL,
    titular BOOLEAN NOT NULL DEFAULT FALSE,
    ha_jugado BOOLEAN NOT NULL DEFAULT FALSE,
    minutos INT DEFAULT NULL,
    goles INT NOT NULL DEFAULT 0,
    asistencias INT NOT NULL DEFAULT 0,
    amarillas INT NOT NULL DEFAULT 0,
    rojas INT NOT NULL DEFAULT 0,
    autogoles INT NOT NULL DEFAULT 0,
    porteria_cero BOOLEAN NOT NULL DEFAULT FALSE,
    puntos INT NOT NULL DEFAULT 0,
    synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_partido_jugador (partido_id, jugador_id),
    KEY idx_fantasy_stats_jugador (jugador_id),
    KEY idx_fantasy_stats_partido (partido_id),
    FOREIGN KEY (partido_id) REFERENCES partidos(id) ON DELETE CASCADE,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
);

INSERT INTO equipos (nombre, ciudad, fundacion, estadio) VALUES
('Real Madrid', 'Madrid', 1902, 'Santiago Bernabéu'), 
('FC Barcelona', 'Barcelona', 1899, 'Spotify Camp Nou'), 
('Atlético de Madrid', 'Madrid', 1903, 'Cívitas Metropolitano'), 
('Sevilla FC', 'Sevilla', 1890, 'Ramón Sánchez-Pizjuán'),  
('Real Betis', 'Sevilla', 1907, 'Benito Villamarín'),  
('Valencia CF', 'Valencia', 1919, 'Mestalla'),   
('Athletic Club', 'Bilbao', 1898, 'San Mamés'),  
('Real Sociedad', 'San Sebastián', 1909, 'Reale Arena'), 
('Villarreal CF', 'Villarreal', 1923, 'Estadio de la Cerámica'), 
('CA Osasuna', 'Pamplona', 1920, 'El Sadar'), 
('Levante UD', 'Valencia', 1909, 'Ciutat de València'), 
('Girona FC', 'Girona', 1930, 'Montilivi'),  
('Elche CF', 'Elche', 1923, 'Martínez Valero'),  
('RCD Espanyol', 'Barcelona', 1900, 'RCDE Stadium'), 
('Getafe CF', 'Getafe', 1983, 'Coliseum Alfonso Pérez'), 
('RCD Mallorca', 'Palma', 1916, 'Son Moix'),  
('RC Celta de Vigo', 'Vigo', 1923, 'Abanca Balaídos'), 
('Deportivo Alavés', 'Vitoria', 1921, 'Mendizorrotza'), 
('Real Oviedo', 'Oviedo', 1926, 'Carlos Tartiere'), 
('Rayo Vallecano', 'Madrid', 1924, 'Vallecas'); 

INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES -- real madrid
('Thibaut Courtois', 33, 'Belga', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 89, 'Portero', 1, TRUE),
('Dani Carvajal', 33, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 85, 'Defensa', 2, TRUE),
('Éder Militão', 27, 'Brasileña', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 84, 'Defensa', 3, TRUE),
('David Alaba', 33, 'Austriaca', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 82, 'Defensa', 4, FALSE),
('Antonio Rüdiger', 32, 'Alemana', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 86, 'Defensa', 22, FALSE),
('Dean Huijsen', 20, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 75, 'Defensa', 24, TRUE),
('Fran García', 25, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 80, 'Defensa', 20, FALSE),
('Álvaro Carreras', 22, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 80, 'Defensa', 18, TRUE),
('Jude Bellingham', 22, 'Inglesa', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 91, 'Centrocampista', 5, TRUE),
('Aurelien Tchouaméni', 25, 'Francesa', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 86, 'Centrocampista', 14, TRUE),
('Federico Valverde', 27, 'Uruguaya', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 87, 'Centrocampista', 8, TRUE),
('Arda Guler', 20, 'Turca', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 81, 'Centrocampista', 24, TRUE),
('Dani Ceballos', 29, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 81, 'Centrocampista', 19, FALSE),
('Franco Mastantuono', 18, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 72, 'Centrocampista', 30, FALSE),
('Gonzalo García', 19, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 70, 'Centrocampista', 16, FALSE),
('Eduardo Camavinga', 23, 'Francesa', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 85, 'Centrocampista', 12, FALSE),
('Vinícius Jr', 25, 'Brasileña', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 90, 'Delantero', 7, TRUE),
('Rodrygo Goes', 24, 'Brasileña', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 84, 'Delantero', 11, FALSE),
('Kylian Mbappé', 27, 'Francesa', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 92, 'Delantero', 10, TRUE),
('Endrick', 19, 'Brasileña', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 77, 'Delantero', 9, FALSE),
('Brahim Díaz', 26, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 82, 'Delantero', 21, FALSE);

INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES -- barsa
('Marc-André ter Stegen', 33, 'Alemania', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 88, 'Portero', 1, false),
('Wojciech Szczęsny', 35, 'Polonia', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 80, 'Portero', 25, FALSE),
('Joan García', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 75, 'Portero', 13, true),
('Ronald Araújo', 26, 'Uruguay', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 85, 'Defensa', 4, TRUE),
('Alejandro Balde', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 82, 'Defensa', 3, TRUE),
('Pau Cubarsí', 19, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 78, 'Defensa', 5, true),
('Andreas Christensen', 29, 'Dinamarca', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 83, 'Defensa', 15, false),
('Gerard Martín', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 74, 'Defensa', 18, FALSE),
('Jules Koundé', 26, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 84, 'Defensa', 23, TRUE),
('Eric García', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 81, 'Defensa', 24, TRUE),
('Gavi', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 84, 'Centrocampista', 6, false),
('Pedri', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 86, 'Centrocampista', 8, TRUE),
('Frenkie de Jong', 28, 'Países Bajos', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 87, 'Centrocampista', 21, TRUE),
('Marc Bernal', 20, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 75, 'Centrocampista', 22, FALSE),
('Dani Olmo', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 82, 'Centrocampista', 20, FALSE),
('Fermín López', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 78, 'Centrocampista', 16, FALSE),
('Marc Casadó', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 77, 'Centrocampista', 17, FALSE),
('Lamine Yamal', 18, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 85, 'Delantero', 10, TRUE),
('Robert Lewandowski', 37, 'Polonia', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 90, 'Delantero', 9, TRUE),
('Raphinha', 28, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 83, 'Extremo', 11, TRUE),
('Ferran Torres', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 82, 'Extremo', 7, TRUE),
('Marcus Rashford', 27, 'Inglaterra', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 84, 'Delantero', 14, FALSE),
('Roony Bardghji', 18, 'Suecia', (SELECT id FROM equipos WHERE nombre = 'Fc Barcelona'), 76, 'Delantero', 28, FALSE);

INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES -- atleti
('Jan Oblak', 33, 'Eslovenia', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 90, 'Portero', 13, TRUE),
('Juan Musso', 31, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 79, 'Portero', 1, FALSE),
('José María Giménez', 30, 'Uruguay', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 86, 'Defensa', 2, TRUE),
('Robin Le Normand', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 84, 'Defensa', 24, TRUE),  
('Matteo Ruggeri', 23, 'Italia', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 80, 'Defensa', 3, FALSE),  
('Javi Galán', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 78, 'Defensa', 21, FALSE),  
('Álex Baena', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 82, 'Centrocampista', 10, TRUE),  
('Thiago Almada', 24, 'Argentina', (SELECT id FROM equipos where nombre = 'Atletico de Madrid'), 83, 'Centrocampista', 11, TRUE),  
('Koke', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 81, 'Centrocampista', 6, TRUE),  
('Marcos Llorente', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 85, 'Centrocampista', 14, TRUE),  
('Pablo Barrios', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 77, 'Centrocampista', 8, FALSE),  
('Giuliano Simeone', 22, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 79, 'Delantero', 20, TRUE),  
('Julián Álvarez', 25, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 88, 'Delantero', 19, TRUE),  
('Alexander Sørloth', 29, 'Noruega', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 84, 'Delantero', 9, FALSE),  
('Giacomo Raspadori', 25, 'Italia', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 80, 'Delantero', 22, FALSE),
('Nahuel Molina', 27, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 79, 'Defensa', 16, TRUE),
('David Hancko', 27, 'Eslovaquia', (SELECT id FROM equipos WHERE nombre = 'Atletico de Madrid'), 83, 'Defensa', 17, FALSE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Odysseas Vlachodimos', 31, 'Grecia', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 72, 'Portero', 1, TRUE),  
('Ørjan Nyland', 34, 'Noruega', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 74, 'Portero', 13, FALSE),  
('Álvaro Fernández', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 65, 'Portero', 25, FALSE),  
('Alberto Flores', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 65, 'Portero', 31, FALSE),  

('José Ángel Carmona', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 81, 'Defensa', 2, TRUE),  
('César Azpilicueta', 36, 'España', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 82, 'Defensa', 3, TRUE),  
('Kike Salas', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 75, 'Defensa', 4, TRUE),  
('Tanguy Nianzou', 23, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 75, 'Defensa', 5, TRUE),  
('Gabriel Suazo', 27, 'Chile', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 74, 'Defensa', 12, TRUE),  

('Nemanja Gudelj', 33, 'Serbia', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 75, 'Centrocampista', 6, TRUE),  
('Lucien Agoumé', 23, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 75, 'Centrocampista', 18, FALSE),  
('Djibril Sow', 28, 'Suiza', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 76, 'Centrocampista', 20, FALSE),  

('Isaac Romero', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 74, 'Delantero', 7, TRUE),  
('Rubén Vargas', 27, 'Suiza', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 75, 'Extremo', 11, FALSE),  
('Akor Adams', 24, 'Nigeria', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 73, 'Delantero', 9, TRUE),  
('Adnan Januzaj', 30, 'Bélgica', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 74, 'Extremo', 24, FALSE),  
('Alexis Sánchez', 36, 'Chile', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 75, 'Delantero', 10, FALSE),  
('Chidera Ejuke', 27, 'Nigeria', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 74, 'Extremo', 21, FALSE);





INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES -- betis
('Pau López', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 79, 'Portero', 25, TRUE),  
('Álvaro Valles', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 79, 'Portero', 1, FALSE),  

('Héctor Bellerín', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 76, 'Defensa', 2, TRUE),  
('Diego Llorente', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 80, 'Defensa', 3, TRUE),  
('Natan', 24, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 79, 'Defensa', 4, TRUE),  
('Marc Bartra', 34, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 79, 'Defensa', 5, TRUE),  
('Sergi Altimira', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 75, 'Centrocampista', 6, TRUE),  
('Junior Firpo', 29, 'República Dominicana', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 75, 'Defensa', 23, FALSE),  

('Pablo Fornals', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 80, 'Centrocampista', 8, TRUE),  
('Abde Ezzalzouli', 23, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 77, 'Extremo', 10, FALSE),  
('Sofyan Amrabat', 29, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 78, 'Centrocampista', 14, TRUE),  
('Rodrigo Riquelme', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 77, 'Centrocampista/Extremo', 17, TRUE),  
('Nelson Deossa', 25, 'Colombia', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 75, 'Centrocampista', 18, FALSE),  
('Giovani Lo Celso', 29, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 82, 'Centrocampista', 20, TRUE),  
('Marc Roca', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 78, 'Centrocampista', 21, FALSE),  
('Isco', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 84, 'Centrocampista', 22, FALSE),  

('Antony', 25, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 81, 'Extremo', 7, TRUE),  
('Chimy Ávila', 31, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 78, 'Delantero', 9, TRUE),  
('Cucho Hernández', 26, 'Colombia', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 78, 'Delantero', 19, TRUE),  
('Aitor Ruibal', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 76, 'Extremo/Delantero', 24, FALSE),  
('Ángel Ortiz', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 70, 'Defensa', 40, FALSE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES -- getafe
('Jirí Letáček', 26, 'República Checa', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 77, 'Portero', 1, TRUE),  
('David Soria', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 81, 'Portero', 13, TRUE),  

('Djené Dakonam', 33, 'Togo', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 77, 'Defensa', 2, TRUE),  
('Abdel Abqar', 26, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 71, 'Defensa', 3, TRUE),  
('Allan Nyom', 38, 'Camerún', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 72, 'Defensa', 12, FALSE),  
('Diego Rico', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 77, 'Defensa', 16, TRUE),  
('Kiko Femenía', 34, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 74, 'Defensa', 17, FALSE),  
('Juan Iglesias', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 70, 'Defensa', 21, FALSE),  
('Domingos Duarte', 31, 'Portugal', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 74, 'Defensa', 22, TRUE),  
('Davinchi', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 71, 'Defensa', 26, FALSE),  

('Yvan Neyou', 26, 'Camerún', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 68, 'Centrocampista', 4, TRUE),  
('Luis Milla', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 79, 'Centrocampista', 5, TRUE),  
('Mario Martín', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 68, 'Centrocampista', 6, FALSE),  
('Mauro Arambarri', 31, 'Uruguay', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 80, 'Centrocampista', 8, TRUE),  
('Javi Muñoz', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 67, 'Centrocampista', 14, FALSE),  

('Juanmi Jiménez', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 87, 'Delantero', 7, TRUE),  
('Borja Mayoral', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 75, 'Delantero', 9, TRUE),  
('Álex Sancris', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 67, 'Delantero', 18, FALSE),  
('Coba da Costa', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 69, 'Delantero', 20, FALSE),  
('Adrián Liso', 20, 'España', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 63, 'Delantero', 23, FALSE);



INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES -- elche
('Matías Dituro', 38, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 75, 'Portero', 1, TRUE),  
('Iñaki Peña', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 75, 'Portero', 13, FALSE),  
('Alejandro Iturbe', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 68, 'Portero', 31, FALSE),  

('Pedro Bigas', 35, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 71, 'Defensa', 6, TRUE),  
('Víctor Chust', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 74, 'Defensa', 22, TRUE),  
('John Chetauya', 27, 'República Centroafricana', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 70, 'Defensa', 15, FALSE),  
('Bambo Diaby', 26, 'Senegal', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 73, 'Defensa', 24, TRUE),  
('Affengruber', 23, 'Austria', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 75, 'Defensa', 22, TRUE),  
('Adrià Pedrosa', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 75, 'Defensa', 3, TRUE),  
('Héctor Fort', 19, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 68, 'Defensa', 2, FALSE),  

('Rodrigo Mendoza', 20, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 67, 'Centrocampista', 30, TRUE),  
('Aleix Febas', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 70, 'Centrocampista', 14, TRUE),  
('Marc Aguado', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 68, 'Centrocampista', 8, FALSE),  
('Martim Neto', 22, 'Portugal', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 69, 'Centrocampista', 16, FALSE),  
('Ali Houary', 23, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 65, 'Centrocampista', 20, FALSE),  

('Josan Ferrández', 35, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 70, 'Extremo', 11, TRUE),  
('Germán Valera', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 71, 'Extremo', 7, FALSE),  
('Grady Diangana', 25, 'Inglaterra', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 72, 'Extremo', 17, FALSE),  
('Rafa Mir', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 75, 'Delantero', 9, TRUE),  
('André Silva', 28, 'Portugal', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 76, 'Delantero', 19, TRUE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES -- oviedo 
('Horatiu Moldovan', 27, 'Rumanía', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 74, 'Portero', 1, TRUE),
('Aarón Escandell', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 76, 'Portero', 13, TRUE),

('Eric Bailly', 31, 'Costa de Marfil', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 76, 'Defensa', 2, TRUE),
('Rahim Alhassane', 23, 'Níger', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 68, 'Defensa', 3, FALSE),
('David Costas', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 72, 'Defensa', 4, TRUE),
('Dani Calvo', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 69, 'Defensa', 12, TRUE),
('Oier Luengo', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 70, 'Defensa', 15, FALSE),
('David Carmo', 26, 'Angola', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 70, 'Defensa', 16, FALSE),
('Nacho Vidal', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 71, 'Defensa', 22, FALSE),
('Javi López', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 67, 'Defensa', 25, FALSE),

('Alberto Reina', 37, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 68, 'Centrocampista', 5, TRUE),
('Kwasi Sibo', 25, 'Ghana', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 66, 'Centrocampista', 6, TRUE),
('Ilyas Chaira', 23, 'Español', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 70, 'Centrocampista', 7, FALSE),
('Santi Cazorla', 40, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 85, 'Centrocampista', 8, TRUE),
('Hassan', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 69, 'Centrocampista', 10, FALSE),
('Colombatto', 33, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 66, 'Centrocampista', 11, FALSE),
('Ovie Ejaria', 27, 'Inglaterra', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 71, 'Centrocampista', 14, FALSE),
('Brandon Domingues', 25, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 72, 'Centrocampista', 17, TRUE),
('Josip Brekalo', 27, 'Croacia', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 74, 'Centrocampista', 18, FALSE),
('Leander Dendoncker', 31, 'Bélgica', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 79, 'Centrocampista', 20, TRUE),
('Luka Ilić', 26, 'Serbia', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 70, 'Centrocampista', 21, FALSE),

('Fede Viñas', 22, 'Uruguay', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 68, 'Delantero', 9, FALSE),
('Alberto Forés', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 67, 'Delantero', 19, FALSE),
('Salomón Rondón', 35, 'Venezuela', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 75, 'Delantero', 23, TRUE);

INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Paulo Gazzaniga', 33, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 80, 'Portero', 13, TRUE),  
('Juan Carlos', 37, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 75, 'Portero', 1, FALSE),  
('Vladyslav Krapyvtsov', 20, 'Ucrania', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 68, 'Portero', 25, FALSE),  

('Arnau Martínez', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 84, 'Defensa', 4, TRUE),  
('Miguel Gutiérrez', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 79, 'Defensa', 3, TRUE),  
('David López', 35, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 76, 'Defensa', 5, FALSE),  
('Daley Blind', 34, 'Países Bajos', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 79, 'Defensa', 17, FALSE),  
('Alejandro Francés', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 82, 'Defensa', 2, TRUE),  
('Ladislav Krejčí', 25, 'República Checa', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 77, 'Defensa', 18, FALSE),  

('Yangel Herrera', 27, 'Venezuela', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 81, 'Centrocampista', 21, TRUE),  
('Oriol Romeu', 34, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 79, 'Centrocampista', 14, FALSE),  
('Donny van de Beek', 27, 'Países Bajos', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 80, 'Centrocampista', 11, FALSE),  
('Iván Martín', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 82, 'Centrocampista', 6, TRUE),  
('Arthur Melo', 27, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 78, 'Centrocampista', 8, FALSE),  
('Jhon Solís', 23, 'Colombia', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 75, 'Centrocampista', 20, FALSE),  

('Bryan Gil', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 84, 'Delantero', 7, TRUE),  
('Viktor Tsygankov', 27, 'Ucrania', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 83, 'Delantero', 10, FALSE),  
('Abel Ruiz', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 81, 'Delantero', 9, FALSE),  
('Cristhian Stuani', 38, 'Uruguay', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 78, 'Delantero', 22, TRUE),  
('Min‑Su Kim', 29, 'Corea del Sur', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 75, 'Delantero', 19, FALSE);

INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Stole Dimitrievski', 31, 'Macedonia del Norte', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 75, 'Portero', 1, TRUE),  
('Julen Agirrezabala', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 70, 'Portero', 25, FALSE),  

('José Copete', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 76, 'Defensa', 3, TRUE),  
('Mouctar Diakhaby', 29, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 78, 'Defensa', 4, TRUE),  
('César Tárrega', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 74, 'Defensa', 5, TRUE),  
('José Gayà', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 81, 'Defensa', 14, TRUE),  
('Eray Cömert', 27, 'Suiza', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 75, 'Defensa', 24, FALSE),  
('Dimitri Foulquier', 32, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 73, 'Defensa', 20, FALSE),  
('Thierry Rendall', 29, 'Portugal', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 72, 'Defensa', 12, FALSE),  
('Jesús Vázquez', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 70, 'Defensa', 21, FALSE),  

('Pepelu', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 77, 'Centrocampista', 18, TRUE),  
('Javi Guerra', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 76, 'Centrocampista', 8, TRUE),  
('Baptiste Santamaría', 27, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 75, 'Centrocampista', 22, FALSE),  
('Filip Ugrinić', 24, 'Croacia', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 74, 'Centrocampista', 23, FALSE),  
('André Almeida', 28, 'Portugal', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 75, 'Centrocampista', 10, FALSE),  
('Dani Raba', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 74, 'Centrocampista', 19, FALSE),  

('Arnaut Danjuma', 28, 'Países Bajos', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 78, 'Delantero', 7, TRUE),  
('Luis Rioja', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 78, 'Delantero', 11, FALSE),  
('Hugo Duro', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 78, 'Delantero', 9, TRUE),  
('Lucas Beltrán', 23, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 76, 'Delantero', 15, FALSE);



INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Dani Cárdenas', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 75, 'Portero', 1, TRUE),  
('Augusto Batalla', 29, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 74, 'Portero', 13, FALSE),  

('Andrei Rațiu', 27, 'Rumanía', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 79, 'Defensa', 2, TRUE),  
('Pep Chavarría', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 78, 'Defensa', 3, TRUE),  
('Luiz Felipe', 28, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 80, 'Defensa', 5, TRUE),  
('Abdul Mumin', 27, 'Ghana', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 77, 'Defensa', 16, TRUE),  
('Iván Balliu', 33, 'Albania', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 76, 'Defensa', 20, FALSE),  
('Florian Lejeune', 34, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 75, 'Defensa', 24, FALSE),  
('Jozhua Vertrouwd', 21, 'Países Bajos', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 70, 'Defensa', 33, FALSE),  

('Pedro Díaz', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 76, 'Centrocampista', 4, TRUE),  
('Pathé Ciss', 31, 'Senegal', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 77, 'Centrocampista', 6, TRUE),  
('Isi Palazón', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 85, 'Centrocampista', 7, TRUE),  
('Óscar Trejo', 37, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 83, 'Centrocampista', 8, FALSE),  
('Gerard Gumbau', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 76, 'Centrocampista', 15, FALSE),  
('Unai López', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 85, 'Centrocampista', 17, FALSE),  
('Álvaro García', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 83, 'Centrocampista', 18, TRUE),  
('Óscar Valentín', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 77, 'Centrocampista', 23, FALSE),  
('Samu Becerra', 19, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 68, 'Centrocampista', 28, FALSE),  
('Diego Méndez', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 70, 'Centrocampista', 29, FALSE),  

('Alemão', 27, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 75, 'Delantero', 9, TRUE),  
('Sergio Camello', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 80, 'Delantero', 10, FALSE),  
('Jorge de Frutos', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 83, 'Delantero', 19, FALSE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Marko Dmitrović', 33, 'Serbia', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 79, 'Portero', 13, TRUE),  
('Ángel Fortuño', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 68, 'Portero', 1, FALSE),  

('Leandro Cabrera', 34, 'Uruguay', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 78, 'Defensa', 6, TRUE),  
('Fernando Calero', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 76, 'Defensa', 5, FALSE),  
('Carlos Romero', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 75, 'Defensa', 22, TRUE),  
('Marash Kumbulla', 25, 'Albania', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 77, 'Defensa', 4, FALSE),  
('Omar El Hilali', 22, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 75, 'Defensa', 23, FALSE),  
('José Salinas', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 71, 'Defensa', 12, FALSE),  
('Clemens Riedel', 22, 'Alemania', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 70, 'Defensa', 38, FALSE),  

('Pol Lozano', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 75, 'Centrocampista', 10, TRUE),  
('Edu Expósito', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 76, 'Centrocampista', 8, FALSE),  
('José Gragera', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 75, 'Centrocampista', 15, FALSE),  
('Urko González de Zárate', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 74, 'Centrocampista', 19, FALSE),  
('Král Alex', 26, 'República Checa', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 74, 'Centrocampista', 20, FALSE),  

('Javi Puado', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 78, 'Delantero', 7, TRUE),  
('Antoniu Roca', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 74, 'Delantero', 31, FALSE),  
('Roberto Fernández', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 72, 'Delantero', 9, FALSE),  
('Pere Milla', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 73, 'Delantero', 11, FALSE),  
('Kike García', 36, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 76, 'Delantero', 19, FALSE),  
('Tyrhys Dolan', 24, 'Inglaterra', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 73, 'Delantero', 24, FALSE);

INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Luiz Júnior', 24, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 78, 'Portero', 1, TRUE),  
('Diego Conde', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 75, 'Portero', 13, FALSE),  
('Arnau Tenas', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 70, 'Portero', 31, FALSE),  

('Adrià Altimira', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 80, 'Defensa', 3, TRUE),  
('Rafa Marín', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 77, 'Defensa', 4, TRUE),  
('Willy Kambwala', 21, 'Francia', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 75, 'Defensa', 5, FALSE),  
('Juan Foyth', 27, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 82, 'Defensa', 8, TRUE),  
('Sergi Cardona', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 76, 'Defensa', 23, FALSE),  
('Pau Navarro', 20, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 74, 'Defensa', 26, FALSE),  

('Manor Solomon', 26, 'Israel', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 77, 'Centrocampista', 6, TRUE),  
('Dani Parejo', 37, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 79, 'Centrocampista', 10, TRUE),  
('Ilias Akhomach', 21, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 78, 'Centrocampista', 11, FALSE),  
('Santi Comesaña', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 78, 'Centrocampista', 14, FALSE),  
('Thomas Partey', 32, 'Ghana', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 81, 'Centrocampista', 16, FALSE),  
('Pape Gueye', 26, 'Senegal', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 79, 'Centrocampista', 18, TRUE),  
('Alberto Moleiro', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 80, 'Centrocampista', 20, TRUE),  
('Alfonso Pedraza', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 77, 'Centrocampista', 24, FALSE),  

('Gerard Moreno', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 85, 'Delantero', 7, TRUE),  
('Georges Mikautadze', 25, 'Georgia', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 84, 'Delantero', 9, FALSE),  
('Tajon Buchanan', 26, 'Canadá', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 82, 'Delantero', 17, FALSE),  
('Nicolas Pépé', 30, 'Costa de Marfil', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 81, 'Delantero', 19, FALSE),  
('Ayoze Pérez', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 80, 'Delantero', 22, FALSE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Antonio Sivera', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 75, 'Portero', 1, TRUE),  
('Raúl Fernández', 37, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 74, 'Portero', 13, FALSE),  
('Grégoire Swiderski', 20, 'Canadá', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 68, 'Portero', 31, FALSE),  
('Rúben Montero', 20, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 68, 'Portero', 33, FALSE),  

('Facundo Garcés', 26, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 76, 'Defensa', 2, TRUE),  
('Youssef Lekhedim', 20, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 70, 'Defensa', 3, FALSE),  
('Jon Pacheco', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 75, 'Defensa', 17, FALSE),  
('Nahuel Tenaglia', 29, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 77, 'Defensa', 14, TRUE),  
('Moussa Diarra', 24, 'Mali', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 74, 'Defensa', 22, FALSE),  

('Antonio Blanco', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 78, 'Centrocampista', 8, TRUE),  
('Ander Guevara', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 76, 'Centrocampista', 6, FALSE),  
('Jon Guridi', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 76, 'Centrocampista', 5, TRUE),  
('Carles Aleñá', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 77, 'Centrocampista', 10, TRUE),  
('Calebe', 25, 'Brasil', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 74, 'Centrocampista', 20, FALSE),  

('Toni Martínez', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 79, 'Delantero', 11, TRUE),  
('Abderrahman Rebbach', 27, 'Argelia', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 75, 'Delantero', 21, TRUE),  
('Lucas Boyé', 27, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 80, 'Delantero', 15, FALSE),  
('Carlos Vicente', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 78, 'Delantero', 7, FALSE);



INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Mathew Ryan', 33, 'Australia', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 80, 'Portero', 1, TRUE),  
('Pablo Campos', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 70, 'Portero', 13, FALSE),  

('Alan Matturro', 21, 'Uruguay', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 75, 'Defensa', 2, TRUE),  
('Matías Moreno', 22, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 73, 'Defensa', 3, TRUE),  
('Adrián Dela', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 71, 'Defensa', 4, FALSE),  
('Unai Elgezabal', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 70, 'Defensa', 5, TRUE),  
('Jorge Cabello', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 68, 'Defensa', 14, FALSE),  

('Manu Sánchez', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 74, 'Defensa', 6, TRUE),  
('Diego Pampín', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 70, 'Defensa', 22, FALSE),  
('Jeremy Toljan', 31, 'Alemania', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 72, 'Defensa', 17, FALSE),  
('Víctor García', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 70, 'Defensa', 20, FALSE),  

('Oriol Rey', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 73, 'Centrocampista', 16, TRUE),  
('Kervin Arriaga', 27, 'Honduras', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 74, 'Centrocampista', 8, TRUE),  
('Jon Ander Olasagasti', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 73, 'Centrocampista', 10, FALSE),  
('Pablo Martínez', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 72, 'Centrocampista', 12, FALSE),  
('Unai Vencedor', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 75, 'Centrocampista', 7, TRUE),  

('Roger Brugué', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 71, 'Delantero', 24, FALSE),  
('Carlos Álvarez', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 77, 'Delantero', 18, TRUE),  
('Iker Losada', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 70, 'Delantero', 21, FALSE),  
('Karl Etta Eyong', 22, 'Camerún', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 78, 'Delantero', 9, TRUE),  
('Iván Romero', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 75, 'Delantero', 15, FALSE),  
('Goduine Koyalipou', 25, 'República Centroafricana', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 72, 'Delantero', 19, FALSE),  
('Carlos Espí', 20, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 70, 'Delantero', 11, FALSE),  
('José Luis Morales', 36, 'España', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 74, 'Delantero', 20, TRUE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Unai Simón', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 84, 'Portero', 1, TRUE),  
('Julen Agirrezabala', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 78, 'Portero', 13, FALSE),  

('Dani Vivian', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 82, 'Defensa', 21, TRUE),  
('Aitor Paredes', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 79, 'Defensa', 4, TRUE),  
('Yeray Álvarez', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 81, 'Defensa', 12, TRUE),  
('Unai Núñez', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 78, 'Defensa', 25, FALSE),  
('Íñigo Lekue', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 77, 'Defensa', 2, FALSE),  
('Óscar de Marcos', 36, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 76, 'Defensa', 18, FALSE),  

('Mikel Vesga', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 79, 'Centrocampista', 20, TRUE),  
('Beñat Prados', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 75, 'Centrocampista', 27, FALSE),  
('Mikel Jauregizar', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 74, 'Centrocampista', 18, TRUE),  
('Iñigo Ruiz de Galarreta', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 78, 'Centrocampista', 8, FALSE),  
('Oihan Sancet', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 82, 'Centrocampista', 10, TRUE),  
('Unai Gómez', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 75, 'Centrocampista', 23, FALSE),  

('Nico Williams', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 85, 'Delantero', 10, TRUE),  
('Iñaki Williams', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 84, 'Delantero', 9, TRUE),  
('Gorka Guruzeta', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 76, 'Delantero', 11, FALSE),  
('Maroan Sannadi', 24, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 75, 'Delantero', 19, FALSE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Leo Román', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 80, 'Portero', 1, TRUE),  
('Lucas Bergström', 23, 'Finlandia', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 72, 'Portero', 13, FALSE),  
('Iván Cuéllar', 41, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 68, 'Portero', 4, FALSE),  

('Marash Kumbulla', 25, 'Albania', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 77, 'Defensa', 24, TRUE),  
('Martin Valjent', 29, 'Eslovaquia', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 78, 'Defensa', 21, TRUE),  
('Antonio Raíllo', 34, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 75, 'Defensa', 27, TRUE),  
('David López', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 70, 'Defensa', 22, FALSE),  
('Johan Mojica', 33, 'Colombia', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 74, 'Defensa', 3, FALSE),  
('Toni Lato', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 74, 'Defensa', 23, FALSE),  
('Pablo Maffeo', 28, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 76, 'Defensa', 2, TRUE),  
('Mateu Morey', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 75, 'Defensa', 12, TRUE),  

('Samú Costa', 24, 'Portugal', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 78, 'Centrocampista', 5, TRUE),  
('Omar Mascarell', 32, 'Guinea Ecuatorial', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 70, 'Centrocampista', 10, FALSE),  
('Sergi Darder', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 79, 'Centrocampista', 6, TRUE),  
('Antonio Sánchez', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 75, 'Centrocampista', 8, FALSE),  
('Manu Morlanes', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 76, 'Centrocampista', 20, TRUE),  
('Pablo Torre', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 77, 'Centrocampista', 14, TRUE),  
('Dani Rodríguez', 37, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 70, 'Centrocampista', 17, FALSE),  

('Jan Virgili', 19, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 74, 'Delantero', 11, FALSE),  
('Javi Llabrés', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 72, 'Delantero', 19, FALSE),  
('Takuma Asano', 31, 'Japón', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 76, 'Delantero', 7, FALSE),  
('Vedat Muriqi', 31, 'Kosovo', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 81, 'Delantero', 18, TRUE),  
('Mateo Joseph', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 77, 'Delantero', 9, TRUE),  
('Abdón Prats', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 74, 'Delantero', 30, TRUE),  
('Marc Domènech', 18, 'España', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 70, 'Delantero', 20, FALSE);


INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Álex Remiro', 30, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 85, 'Portero', 1, TRUE),  
('Unai Marrero', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 70, 'Portero', 13, FALSE),  

('Igor Zubeldia', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 80, 'Defensa', 5, TRUE),  
('Jon Martín', 19, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 75, 'Defensa', 31, FALSE),  
('Duje Caleta-Car', 29, 'Croacia', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 78, 'Defensa', 16, TRUE),  
('Aritz Elustondo', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 74, 'Defensa', 6, FALSE),  
('Sergio Gómez', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 82, 'Defensa', 17, TRUE),  
('Aihen Muñoz', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 75, 'Defensa', 3, FALSE),  
('Jon Aramburu', 23, 'Venezuela/España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 80, 'Defensa', 2, TRUE),  
('Álvaro Odriozola', 29, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 73, 'Defensa', 20, FALSE),  
('Jon Gorrotxategi', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 76, 'Defensa', 4, TRUE),  
('Mateu Morey', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 75, 'Defensa', 12, TRUE),  

('Carlos Soler', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 84, 'Centrocampista', 18, TRUE),  
('Luka Sucic', 23, 'Croacia', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 82, 'Centrocampista', 24, TRUE),  
('Yangel Herrera', 27, 'Venezuela/España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 81, 'Centrocampista', 12, TRUE),  
('Beñat Turrientes', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 79, 'Centrocampista', 8, FALSE),  
('Brais Méndez', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 85, 'Centrocampista', 23, TRUE),  
('Pablo Marín', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 78, 'Centrocampista', 28, FALSE),  
('Arsen Zakharyan', 22, 'Rusia', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 77, 'Centrocampista', 21, FALSE),  
('Mikel Goti', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 70, 'Centrocampista', 22, FALSE),  
('Ander Barrenetxea', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 85, 'Centrocampista', 7, TRUE),  
('Gonçalo Guedes', 28, 'Portugal', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 80, 'Centrocampista', 11, FALSE),  
('Takefusa Kubo', 24, 'Japón', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 88, 'Centrocampista', 14, TRUE),  

('Mikel Oyarzabal', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 87, 'Delantero', 10, TRUE),  
('Orri Óskarsson', 21, 'Islandia', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 79, 'Delantero', 9, FALSE),  
('Umar Sadiq', 28, 'Nigeria', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 75, 'Delantero', 15, FALSE),  
('Jon Karrikaburu', 20, 'España', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 74, 'Delantero', 19, FALSE);



INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Ionuț Radu', 28, 'Rumania', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 75, 'Portero', 13, TRUE),  
('Iván Villar', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 72, 'Portero', 1, TRUE),  
('Marc Vidal', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 68, 'Portero', 25, FALSE),  

('Javi Rodríguez', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 74, 'Defensa', 32, TRUE),  
('Carl Starfelt', 30, 'Suecia', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 76, 'Defensa', 2, TRUE),  
('Carlos Domínguez', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 71, 'Defensa', 24, FALSE),  
('Yoel Lago', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 69, 'Defensa', 29, FALSE),  
('Manu Fernández', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 70, 'Defensa', 12, FALSE),  
('Marcos Alonso', 34, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 68, 'Defensa', 20, FALSE),  
('Joseph Aidoo', 30, 'Ghana', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 67, 'Defensa', 4, FALSE),  

('Mihailo Ristic', 30, 'Serbia', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 69, 'Defensa', 21, FALSE),  
('Óscar Mingueza', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 75, 'Defensa', 3, TRUE),  
('Sergio Carreira', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 72, 'Defensa', 5, FALSE),  
('Javi Rueda', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 71, 'Defensa', 17, FALSE),  

('Damián Rodríguez', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 70, 'Centrocampista', 14, FALSE),  
('Hugo Sotelo', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 76, 'Centrocampista', 22, TRUE),  
('Ilaix Moriba', 22, 'Guinea', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 74, 'Centrocampista', 6, TRUE),  
('Fran Beltrán', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 73, 'Centrocampista', 8, TRUE),  
('Miguel Román', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 68, 'Centrocampista', 16, FALSE),  

('Bryan Zaragoza', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 77, 'Centrocampista', 15, TRUE),  
('Williot Swedberg', 21, 'Suecia', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 75, 'Centrocampista', 19, FALSE),  
('Hugo Álvarez', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 74, 'Centrocampista', 23, FALSE),  
('Franco Cervi', 31, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 70, 'Centrocampista', 11, FALSE),  
('Jones El-Abdellaoui', 19, 'Marruecos', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 71, 'Centrocampista', 39, FALSE),  

('Ferran Jutglà', 26, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 76, 'Delantero', 9, TRUE),  
('Pablo Durán', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 72, 'Delantero', 18, FALSE),  
('Borja Iglesias', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 74, 'Delantero', 7, TRUE),  
('Iago Aspas', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 80, 'Delantero', 10, TRUE);

INSERT INTO jugadores (nombre, edad, nacionalidad, equipo_id, media_fifa, posicion, dorsal, es_titular) VALUES  
('Sergio Herrera', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 75, 'Portero', 1, TRUE),  
('Aitor Fernández', 34, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 70, 'Portero', 13, FALSE),  

('Enzo Boyomo', 24, 'Camerún', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 76, 'Defensa', 22, TRUE),  
('Jorge Herrando', 24, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 72, 'Defensa', 5, FALSE),  
('Alejandro Catena', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 71, 'Defensa', 24, TRUE),  
('Abel Bretones', 25, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 73, 'Defensa', 23, TRUE),  
('Juan Cruz', 33, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 69, 'Defensa', 3, FALSE),  
('Valentin Rosier', 29, 'Francia', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 74, 'Defensa', 19, TRUE),  

('Lucas Torró', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 72, 'Centrocampista', 6, TRUE),  
('Iker Muñoz', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 70, 'Centrocampista', 8, FALSE),  
('Jon Moncayola', 27, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 75, 'Centrocampista', 7, TRUE),  
('Asier Osambela', 21, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 68, 'Centrocampista', 29, FALSE),  
('Aimar Oroz', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 77, 'Centrocampista', 10, TRUE),  

('Víctor Muñoz', 22, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 72, 'Centrocampista', 21, TRUE),  
('Moi Gómez', 31, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 70, 'Centrocampista', 16, FALSE),  
('Sheraldo Becker', 30, 'Surinam', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 70, 'Centrocampista', 18, FALSE),  
('Rubén García', 32, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 69, 'Centrocampista', 14, FALSE),  
('Iker Benito', 23, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 68, 'Centrocampista', 2, FALSE),  
('Kike Barja', 28, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 68, 'Centrocampista', 11, FALSE),  

('Ante Budimir', 34, 'Croacia', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 74, 'Delantero', 17, TRUE),  
('Raúl García', 34, 'España', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 75, 'Delantero', 9, TRUE);


INSERT INTO entrenadores (nombre, edad, nacionalidad, equipo_id, estilo_juego) VALUES
-- Real Madrid
('Alvaro Arbeloa', 43, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Madrid'), 'Posesión y transiciones rápidas'),

-- FC Barcelona
('Hansi Flick', 60, 'Alemana', (SELECT id FROM equipos WHERE nombre = 'FC Barcelona'), 'Pressing alto y posesión'),

-- Atlético de Madrid
('Diego Simeone', 55, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Atlético de Madrid'), 'Defensa sólida y contragolpe'),

-- Sevilla FC
('Matías Almeyda', 51, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Sevilla FC'), 'Intensidad y presión alta'),

-- Real Betis
('Manuel Pellegrini', 72, 'Chilena', (SELECT id FROM equipos WHERE nombre = 'Real Betis'), 'Juego ofensivo y posesión'),

-- Valencia CF
('Carlos Corberán', 42, 'Española', (SELECT id FROM equipos WHERE nombre = 'Valencia CF'), 'Presión alta y organización'),

-- Athletic Club
('Ernesto Valverde', 61, 'Española', (SELECT id FROM equipos WHERE nombre = 'Athletic Club'), 'Presión alta y juego directo'),

-- Real Sociedad
('Sergio Francisco', 46, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Sociedad'), 'Posesión y ataque organizado'),

-- Villarreal CF
('Marcelino', 60, 'Española', (SELECT id FROM equipos WHERE nombre = 'Villarreal CF'), 'Orden defensivo y contragolpe'),

-- CA Osasuna
('Alessio Lisci', 40, 'Italiana', (SELECT id FROM equipos WHERE nombre = 'CA Osasuna'), 'Intensidad y juego vertical'),

-- Levante UD
('Julián Calero', 55, 'Española', (SELECT id FROM equipos WHERE nombre = 'Levante UD'), 'Juego ofensivo por bandas'),

-- Girona FC
('Míchel', 50, 'Española', (SELECT id FROM equipos WHERE nombre = 'Girona FC'), 'Posesión y ataque organizado'),

-- Elche CF
('Eder Sarabia', 44, 'Española', (SELECT id FROM equipos WHERE nombre = 'Elche CF'), 'Presión alta e intensidad'),

-- RCD Espanyol
('Manolo González', 46, 'Española', (SELECT id FROM equipos WHERE nombre = 'RCD Espanyol'), 'Equilibrio y contragolpe'),

-- Getafe CF
('José Bordalás', 61, 'Española', (SELECT id FROM equipos WHERE nombre = 'Getafe CF'), 'Intensidad y juego directo'),

-- RCD Mallorca
('Jagoba Arrasate', 47, 'Española', (SELECT id FROM equipos WHERE nombre = 'RCD Mallorca'), 'Orden defensivo y transiciones'),

-- RC Celta de Vigo
('Claudio Giráldez', 37, 'Española', (SELECT id FROM equipos WHERE nombre = 'RC Celta de Vigo'), 'Posesión y creación'),

-- Deportivo Alavés
('Eduardo Coudet', 51, 'Argentina', (SELECT id FROM equipos WHERE nombre = 'Deportivo Alavés'), 'Defensa sólida y balón parado'),

-- Real Oviedo
('Luis Carrión', 49, 'Española', (SELECT id FROM equipos WHERE nombre = 'Real Oviedo'), 'Intensidad y presión'),

-- Rayo Vallecano
('Iñigo Pérez', 37, 'Española', (SELECT id FROM equipos WHERE nombre = 'Rayo Vallecano'), 'Posesión y juego combinativo');

ALTER TABLE usuarios_jugadores
    ADD CONSTRAINT fk_usuarios_jugadores_jugador
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE;

ALTER TABLE mercado
    ADD CONSTRAINT fk_mercado_jugador
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE;

ALTER TABLE transacciones
    ADD CONSTRAINT fk_transacciones_jugador
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE;

INSERT INTO mercado (jugador_id, precio, disponible)
SELECT j.id,
       CASE
           WHEN j.media_fifa >= 90 THEN 22000000
           WHEN j.media_fifa >= 85 THEN 15000000
           WHEN j.media_fifa >= 80 THEN 9000000
           WHEN j.media_fifa >= 75 THEN 5500000
           ELSE 3000000
       END AS precio,
       TRUE
FROM jugadores j;

