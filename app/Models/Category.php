<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Category lookup model.
 */
class Category extends Model
{
    /**
     * @return array<int, array{category_id:int, name:string}>
     */
    public function all(): array
    {
        $stmt = $this->db->prepare('SELECT category_id, name FROM categories ORDER BY name ASC');
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = ['category_id' => (int) $row['category_id'], 'name' => $row['name']];
        }
        $stmt->close();

        return $rows;
    }
}
