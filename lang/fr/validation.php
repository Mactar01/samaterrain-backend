<?php

return [
    'unique' => 'Le champ :attribute est déjà utilisé.',
    'required' => 'Le champ :attribute est obligatoire.',
    'confirmed' => 'La confirmation du mot de passe ne correspond pas.',
    'min' => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un symbole.',
        'uncompromised' => 'Le mot de passe renseigné est vulnérable. Veuillez en choisir un autre.',
    ],

    'attributes' => [
        'email' => 'adresse e-mail',
        'phone' => 'numéro de téléphone',
        'password' => 'mot de passe',
        'business_name' => 'nom du complexe',
    ],
];
