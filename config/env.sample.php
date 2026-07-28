<?php
// Extrait les variables d'environnement de la BDD et de l'Admin
return [
    'DB_HOST' => getenv('DB_HOST') ?: 'localhost',
    'DB_NAME' => getenv('DB_NAME') ?: 'legentlemanpub',
    'DB_USER' => getenv('DB_USER') ?: 'root',
    'DB_PASS' => getenv('DB_PASS') ?: '',
    'ADMIN_USER' => getenv('ADMIN_USER') ?: 'admin',
    'ADMIN_PASS_HASH' => getenv('ADMIN_PASS_HASH') ?: '$2b$10$O3EqPTpkCa0aiiq/429CrOiS812Zq88cP2OFrccx0pvcn0tEXw.LO',
];
