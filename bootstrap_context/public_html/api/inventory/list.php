<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    $userId = trailbound_require_auth();
    $db = trailbound_db();

    $chests = $db->prepare(
        'SELECT uc.id AS user_chest_id, uc.opened_at, uc.created_at, c.name, c.rarity, c.description
         FROM user_chests uc
         JOIN chests c ON c.id = uc.chest_id
         WHERE uc.user_id = :user_id
         ORDER BY uc.opened_at IS NOT NULL, uc.created_at DESC'
    );
    $chests->execute(['user_id' => $userId]);

    $items = $db->prepare(
        'SELECT ui.id AS user_item_id, ui.is_equipped, ui.acquired_at, i.name, i.item_type, i.rarity, i.description
         FROM user_items ui
         JOIN items i ON i.id = ui.item_id
         WHERE ui.user_id = :user_id
         ORDER BY ui.is_equipped DESC, ui.acquired_at DESC'
    );
    $items->execute(['user_id' => $userId]);

    trailbound_api_success([
        'chests' => $chests->fetchAll(),
        'items' => $items->fetchAll(),
    ]);
});
