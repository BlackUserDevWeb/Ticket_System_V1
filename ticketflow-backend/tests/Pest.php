<?php

/*
| tests/Pest.php — bootstrap PestPHP.
| groups() attache automatiquement TestCase + migrations aux dossiers Feature/Unit.
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit');

/*
| Helper global : crée un utilisateur avec token Sanctum prêt à l'emploi.
| Évite la répétition auth()->login(...) dans chaque test d'API.
*/
function apiHeaders(\App\Models\User $user): array
{
    $token = $user->createToken('test')->plainTextToken;
    return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
}
