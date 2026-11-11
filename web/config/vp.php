<?php

// Réglages de la plateforme : chemins vers le moteur Python et le navigateur.
return [
    // Racine du projet Python (pipeline.py, scanners/, results/, config/).
    'root' => env('VP_ROOT', dirname(base_path())),
    'python' => env('VP_PYTHON', 'python3'),
    'chrome' => env('VP_CHROME', 'google-chrome'),
];
