<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Brand lookup model.
 */
class Brand extends Model
{
    /**
     * @return array<int, array{brand_id:int, name:string}>
     */
    public function all(): array
    {
        $stmt = $this->db->prepare('SELECT brand_id, name FROM brands ORDER BY name ASC');
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = ['brand_id' => (int) $row['brand_id'], 'name' => $row['name']];
        }
        $stmt->close();

        return $rows;
    }
}
